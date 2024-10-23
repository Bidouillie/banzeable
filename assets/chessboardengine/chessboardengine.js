
import '../vendor/cm-chessboard/assets/chessboard.css';
import '../vendor/cm-chessboard/assets/extensions/markers/markers.css';

import { Chessboard, COLOR, FEN, INPUT_EVENT_TYPE } from 'cm-chessboard'
import { Markers } from 'cm-chessboard/src/extensions/markers/Markers.js';
import { Chess } from 'chess.js';

export class ChessboardEngine {

    #chess = new Chess();
    #board;

    // moves to be played
    #sanMoves = [];

    /**
     * @callback movePlayedCallback
     * @param {{'moveValidation': boolean, 'lastMove': boolean}} event
     */
    #movePlayedCallback;

    // number of miliseconds to wait for next move, or false if no auto mode
    #autoNext;

    get orientation() {
        return this.#board.getOrientation() === COLOR.white ? 'w' : 'b';
    }

    constructor(htmlElement, moves = [], orientation = 'w', fen = FEN.start) {

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

        if (true) {
            this.#chess.load(fen);
            for (let key in moves) {
                this.#chess.move(moves[key]);
            }
        } else {
            this.#chess.loadPgn();
        }

        this.#sanMoves = this.#chess.history().slice().reverse();
        let moveObject;
        do {
            moveObject = this.#chess.undo();
        } while (moveObject !== null);
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

        this.#movePlayedCallback = moveHandler;

        this.#autoNext = autoNext;

        if (this.orientation !== this.#chess.turn()) {
            this.#playMove(this.#sanMoves.pop());
            this.#fireMoveEvent();
        }

        this.#enableMoveInput(mode === 'analysis' ? null : this.#board.getOrientation());
    }

    #enableMoveInput(color = null) {
        this.#board.enableMoveInput((event) => {
            return this.#inputHandler(event);
        }, color);
    }

    disablePlayableMove() {
        this.#movePlayedCallback = null;
        this.#autoNext = false;
        this.#board.disableMoveInput();
    }

    previousMove() {
        const move = this.#undoMove();
        if (move) {
            this.#sanMoves.push(move);
        }
    }

    nextMove() {
        this.#playMove(this.#sanMoves.pop());
    }

    gotoMove(index) {

        if (index < 0) {
            throw new Error("Index should be greater than or equal to 0");
        }
        switch (true) {
            case index > this.#chess.history().length:
                // TODO check case infinite loop
                do {
                    this.#playMove(this.#sanMoves.pop());
                } while (index > this.#chess.history().length);
                break;
            case index < this.#chess.history().length:
                // TODO check case infinite loop
                do {
                    const move = this.#undoMove();
                    if (move) {
                        this.#sanMoves.push(move);
                    }
                } while (index < this.#chess.history().length);
                break;
        }
    }

    #inputHandler(event) {

        switch (event.type) {
            case INPUT_EVENT_TYPE.moveInputStarted:
                return true;
            case INPUT_EVENT_TYPE.validateMoveInput:

                try {
                    const moveObject = { from: event.squareFrom, to: event.squareTo };
                    if (event.piece.substring(1) === 'p' && (event.squareTo.substring(1) === '1' || event.squareTo.substring(1) === '8')) {
                        // TODO Get promotion choice
                        moveObject.promotion = 'q';
                    }
                    const move = this.#chess.move(moveObject);

                    // Castling and en passant
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
                    }
                } catch (error) {
                    if (!error.message.startsWith("Invalid move")) {
                        console.log(error);
                        throw error;
                    }
                    return false;
                }
                return true;
            case INPUT_EVENT_TYPE.moveInputFinished:

                if (event.legalMove) {
                    // Board update in case of promotion
                    const lastMove = this.#chess.history({ verbose: true }).at(-1);
                    if (lastMove.flags.includes('p')) {
                        this.#board.setPiece(lastMove.to, lastMove.color + lastMove.promotion);
                    }

                    let moveValidation;
                    if (this.#sanMoves.length > 0) {
                        moveValidation = this.#validateOrUndoMove(lastMove.san);
                    }

                    this.#fireMoveEvent({ moveValidation, 'lastMove': this.#sanMoves.length < 2 });
                }

                break;
        }
    }

    #fireMoveEvent(event) {
        if (this.#movePlayedCallback) {
            this.#movePlayedCallback({ index: this.#chess.history().length, ...event });
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
        }
    }

    #undoMove() {

        /**
         * Play chess move
         */
        const move = this.#chess.undo();

        if (move) {

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

    #validateOrUndoMove(notation) {

        if (this.#autoNext !== false) {
            this.#board.disableMoveInput();
        }

        if (notation === this.#sanMoves.slice(-1)[0]) {
            this.#sanMoves.pop();
            if (this.#autoNext !== false) {
                setTimeout(() => {
                    this.#playMove(this.#sanMoves.pop());
                    this.#fireMoveEvent();
                    this.#enableMoveInput(this.#board.getOrientation());
                }, this.#autoNext);
            }
            return true;
        } else {
            if (this.#autoNext !== false) {
                setTimeout(() => {
                    this.#undoMove();
                    this.#fireMoveEvent();
                    this.#enableMoveInput(this.#board.getOrientation());
                }, this.#autoNext);
            }
            return false;
        }
    }
}