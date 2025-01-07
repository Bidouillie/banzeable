
import '../vendor/cm-chessboard/assets/chessboard.css';
import '../vendor/cm-chessboard/assets/extensions/markers/markers.css';

import { Chessboard, COLOR, FEN, INPUT_EVENT_TYPE } from 'cm-chessboard'
import { Markers } from 'cm-chessboard/src/extensions/markers/Markers.js';
import { Chess } from '@jackstenglein/chess';

export class ChessboardEngine {

    #chess = new Chess();
    #board;

    #mode;

    /**
     * @callback movePlayedCallback
     * @param {{index: number, moveValidation: ?boolean, lastMove: ?boolean}} event
     */
    #movePlayedCallback;

    #halfMovesProgress = 0;

    // number of miliseconds to wait for next move, or false if no auto mode
    #autoNext;

    get orientation() {
        return this.#board.getOrientation() === COLOR.white ? 'w' : 'b';
    }

    constructor(htmlElement, orientation = 'w', PGN = null, moves = [], fen = FEN.start) {

        if (orientation !== 'w' && orientation !== 'b') {
            throw new Error("Orientation should be 'w' (white) or 'b' (black)")
        }

        this.#board = new Chessboard(htmlElement, {
            position: fen,
            orientation: orientation === 'w' ? COLOR.white : COLOR.black,
            assetsUrl: '/cm-chessboard/assets/',
            extensions: [{ class: Markers }],
            style: {
                pieces: {
                    file: '/cm-chessboard/assets/pieces/staunty.svg',
                },
                animationDuration: 200,
            },
        });

        if (PGN) {
            this.#chess.loadPgn(PGN);
        } else {
            this.#chess.load(fen);

            for (let key in moves) {
                this.#chess.move(moves[key]);
            }
        }

        if (this.#chess.firstMove()) {
            this.#chess.seek(this.#chess.firstMove().previous);
        }
    }

    /**
     * @param {movePlayedCallback} moveHandler
     * @param {number | false} autoNext
     * @param {'analysis' | 'variation'} mode
     */
    enablePlayableMove(moveHandler, autoNext = false, mode = 'analysis') {

        if (mode !== 'analysis' && mode !== 'variation') {
            throw new Error("Mode should be 'analysis' or 'variation'")
        }

        this.#mode = mode;

        this.#movePlayedCallback = moveHandler;

        this.#autoNext = autoNext;

        if (this.#mode === 'variation' && this.orientation !== this.#chess.turn()) {
            if (this.#playMove(this.#chess.nextMove().san)) {
                this.#halfMovesProgress++;
                this.#fireMoveEvent();
            }
        }

        this.#enableMoveInput();
    }

    #enableMoveInput() {
        this.#board.enableMoveInput((event) => {
            return this.#inputHandler(event);
        }, this.#mode === 'analysis' ? this.#chess.turn() : this.#board.getOrientation());
    }

    #switchTurn() {
        this.#board.disableMoveInput();
        this.#board.enableMoveInput((event) => {
            return this.#inputHandler(event);
        }, this.#chess.turn());
    }

    disablePlayableMove() {
        this.#movePlayedCallback = null;
        this.#autoNext = false;
        this.#board.disableMoveInput();
    }

    previousMove() {
        const move = this.#undoMove();
        if (move) {
            this.#board.disableMoveInput();
            return true;
        }
        return false;
    }

    nextMove() {
        const move = this.#chess.nextMove();
        if (move) {
            const movePlayed = this.#playMove(move.san);
            if (movePlayed && move.ply === this.#halfMovesProgress) {
                this.#enableMoveInput();
            } else {
                this.#board.disableMoveInput();
            }
            return movePlayed;
        }
    }

    gotoEnd() {
        const index = this.#mode === 'variation' ? this.#halfMovesProgress : this.#chess.history().length;
        this.gotoMove(index);
        return index;
    }

    gotoMove(index) {

        if (index < 0) {
            throw new Error("Index should be greater than or equal to 0");
        }

        const currentMove = this.#chess.currentMove();
        const currentIndex = currentMove ? currentMove.ply : 0;
        switch (true) {
            case index > currentIndex:
                let nextMove;
                do {
                    nextMove = this.#chess.nextMove();
                    if (nextMove) {
                        this.#playMove(nextMove.san);
                    }
                } while (nextMove && nextMove.ply < index);
                break;
            case index < currentIndex:
                let previousMove;
                do {
                    previousMove = this.#undoMove();
                } while (previousMove && previousMove.ply > index);
                break;
            default:
                return false;
        }
        if (index === this.#halfMovesProgress) {
            this.#enableMoveInput();
        } else {
            this.#board.disableMoveInput();
        }
        return true;
    }

    #inputHandler(event) {

        switch (event.type) {
            case INPUT_EVENT_TYPE.moveInputStarted:
                return true;
            case INPUT_EVENT_TYPE.validateMoveInput:

                const moveObject = { from: event.squareFrom, to: event.squareTo };
                if (event.piece.substring(1) === 'p' && (event.squareTo.substring(1) === '1' || event.squareTo.substring(1) === '8')) {
                    // TODO Get promotion choice
                    moveObject.promotion = 'q';
                }
                const move = this.#chess.validateMove(moveObject);

                if (move) {
                    switch (true) {
                        // Castling
                        case move.flags.includes('k'):
                        case move.flags.includes('q'):
                            let from;
                            let to;
                            if (move.flags.includes('k')) {
                                from = 'h' + move.from.substring(1);
                                to = 'f' + move.to.substring(1);
                            } else {
                                from = 'a' + move.from.substring(1);
                                to = 'd' + move.to.substring(1);
                            }
                            this.#board.movePiece(from, to, true);
                            break;
                        // En passant
                        case move.flags.includes('e'):
                            this.#board.setPiece(move.to.substring(0, 1) + move.from.substring(1), null);
                            break;
                        // Promotion
                        case move.flags.includes('p'):
                            this.#board.setPiece(move.to, move.color + move.promotion);
                            break;
                    }

                    this.#chess.move(move);
                    return true;
                }

                return false;
            case INPUT_EVENT_TYPE.moveInputFinished:

                if (event.legalMove) {
                    let event = {};

                    if (this.#mode === 'variation') {
                        const move = this.#chess.currentMove();

                        const moveValidation = this.#validateOrUndoMove(move);

                        event = { moveValidation, 'lastMove': moveValidation && move.ply === this.#chess.history().length };
                    } else {
                        this.#switchTurn();
                    }

                    this.#fireMoveEvent(event);
                }

                break;
        }
    }

    #fireMoveEvent(event) {
        if (this.#movePlayedCallback) {
            let move = this.#chess.currentMove();
            this.#movePlayedCallback({ index: move.ply, san: move.san, ...event });
        }
    }

    #playMove(sanMove) {

        if (sanMove) {

            /**
             * Play chess move
             */
            const move = this.#chess.move(sanMove);

            /**
             * Play board move
             */
            this.#board.movePiece(move.from, move.to, true);
            switch (true) {
                case move.flags.includes('k'):
                case move.flags.includes('q'):
                    let from;
                    let to;
                    if (move.flags.includes('k')) {
                        from = 'h' + move.from.substring(1);
                        to = 'f' + move.to.substring(1);
                    } else {
                        from = 'a' + move.from.substring(1);
                        to = 'd' + move.to.substring(1);
                    }
                    this.#board.movePiece(from, to, true);
                    break;
                case move.flags.includes('e'):
                    this.#board.setPiece(move.to.substring(0, 1) + move.from.substring(1), null);
                    break;
                case move.flags.includes('p'):
                    this.#board.setPiece(move.to, move.color + move.promotion);
                    break;
            }
            return true;
        }
        return false;
    }

    #undoMove() {

        const move = this.#chess.currentMove();

        if (move) {

            /**
             * Play chess move
             */
            this.#chess.seek(move.previous);

            /**
             * Play board move
             */
            this.#board.movePiece(move.to, move.from, true);
            switch (true) {
                case move.flags.includes('k'):
                case move.flags.includes('q'):
                    let from;
                    let to;
                    if (move.flags.includes('k')) {
                        from = 'h' + move.from.substring(1);
                        to = 'f' + move.to.substring(1);
                    } else {
                        from = 'a' + move.from.substring(1);
                        to = 'd' + move.to.substring(1);
                    }
                    this.#board.movePiece(to, from, true);
                    break;
                case move.flags.includes('e'):
                    this.#board.setPiece(move.to.substring(0, 1) + move.from.substring(1), move.color + 'p');
                    break;
                case move.flags.includes('p'):
                    this.#board.setPiece(move.from, move.color + 'p');
                    break;
            }

            if (move.flags.includes('c')) {
                this.#board.setPiece(move.to, (move.color === 'w' ? 'b' : 'w') + move.captured);
            }

            return move;
        }
    }

    #validateOrUndoMove(move) {

        if (this.#autoNext !== false) {
            this.#board.disableMoveInput();
        }

        if (this.#chess.isMainline(move, move.previous)) {
            this.#halfMovesProgress++;
            if (this.#autoNext !== false) {
                setTimeout(() => {
                    const move = this.#chess.nextMove();
                    if (move && this.#playMove(move.san)) {
                        this.#halfMovesProgress++;
                        this.#fireMoveEvent();
                        this.#enableMoveInput();
                    }
                }, this.#autoNext);
            }
            return true;
        }

        // TODO stop moves to be sure that the last move played is the incorrect one
        if (this.#autoNext !== false) {
            setTimeout(() => {
                if (this.#undoMove()) {
                    this.#fireMoveEvent();
                    this.#enableMoveInput();
                }
            }, this.#autoNext);
        }

        return false;
    }
}