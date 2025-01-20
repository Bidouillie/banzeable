
import '../vendor/cm-chessboard/assets/chessboard.css';
import '../vendor/cm-chessboard/assets/extensions/arrows/arrows.css';
import '../vendor/cm-chessboard/assets/extensions/markers/markers.css';

import { Chessboard, COLOR, FEN, INPUT_EVENT_TYPE } from 'cm-chessboard'
import { Markers, MARKER_TYPE } from 'cm-chessboard/src/extensions/markers/Markers.js';
import { Arrows, ARROW_TYPE } from 'cm-chessboard/src/extensions/arrows/Arrows.js';
import { Chess } from '@jackstenglein/chess';

export class ChessboardEngine {

    /**
     * @type Chess _chess
     */
    _chess = new Chess();
    _board;

    /**
     * @callback movePlayedCallback
     * @param {{index: number, moveValidation: ?boolean, lastMove: ?boolean}} event
     */
    #movePlayedCallback;

    // number of miliseconds to wait for next move (or to enable next turn), or false if no auto mode
    _autoNext;

    get orientation() {
        return this._board.getOrientation() === COLOR.white ? 'w' : 'b';
    }

    constructor(htmlElement, orientation = 'w', PGN = null, moves = [], fen = FEN.start) {

        if (orientation !== 'w' && orientation !== 'b') {
            throw new Error("Orientation should be 'w' (white) or 'b' (black)")
        }

        this._board = new Chessboard(htmlElement, {
            position: fen,
            orientation: orientation === 'w' ? COLOR.white : COLOR.black,
            assetsUrl: '/cm-chessboard/assets/',
            extensions: [{ class: Markers }, { class: Arrows }],
            style: {
                pieces: {
                    file: '/cm-chessboard/assets/pieces/staunty.svg',
                },
                animationDuration: 200,
            },
        });

        // Play moves to store history
        if (PGN) {
            this._chess.loadPgn(PGN);
        } else {
            this._chess.load(fen);

            for (let key in moves) {
                this._chess.move(moves[key]);
            }
        }

        // Go back to first move
        let firstMove = this._chess.firstMove();
        if (firstMove) {
            this._chess.seek(firstMove.previous);
        }
    }

    _enableMoveInput() {
        this._board.enableMoveInput((event) => {
            return this._inputHandler(event);
        }, this._chess.turn());
    }

    _validateMoveInput(event) {

        const moveObject = { from: event.squareFrom, to: event.squareTo };
        if (event.piece.substring(1) === 'p' && (event.squareTo.substring(1) === '1' || event.squareTo.substring(1) === '8')) {
            // TODO Get promotion choice
            moveObject.promotion = 'q';
        }
        const move = this._chess.validateMove(moveObject);

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
                    this._board.movePiece(from, to, true);
                    break;
                // En passant
                case move.flags.includes('e'):
                    this._board.setPiece(move.to.substring(0, 1) + move.from.substring(1), null);
                    break;
                // Promotion
                case move.flags.includes('p'):
                    this._board.setPiece(move.to, move.color + move.promotion);
                    break;
            }

            this._chess.move(move);
            return true;
        }

        return false;
    }

    _moveInputFinished() {

        this._fireMoveEvent({});
    }

    _inputHandler(event) {

        switch (event.type) {
            case INPUT_EVENT_TYPE.moveInputStarted:
                return true;
            case INPUT_EVENT_TYPE.validateMoveInput:
                return this._validateMoveInput(event);
            case INPUT_EVENT_TYPE.moveInputFinished:
                if (event.legalMove) {
                    this._board.removeMarkers();
                    this._moveInputFinished();
                    this._board.addMarker(MARKER_TYPE.bevel, event.squareFrom);
                    this._board.addMarker(MARKER_TYPE.bevel, event.squareTo);
                }
                break;
        }
    }

    _fireMoveEvent(event) {
        if (this.#movePlayedCallback) {
            let move = this._chess.currentMove();
            this.#movePlayedCallback({ index: move === null ? 0 : move.ply, san: move?.san, ...event });
        }
    }

    _playMoves(sanMoves) {

        if (sanMoves && (typeof sanMoves === 'string' || sanMoves.length > 0)) {

            if (typeof sanMoves === 'string') {
                sanMoves = [sanMoves];
            }

            /**
             * Play chess moves
             */
            let moves = [];
            for (let i = 0; i < sanMoves.length; i++) {
                moves.push(this._chess.move(sanMoves[i]));
            }

            /**
             * Play board moves
             */
            this._board.removeMarkers();
            if (moves.length === 1) {
                this._board.addMarker(MARKER_TYPE.bevel, moves[0].from);
            }
            for (let i = 0; i < moves.length; i++) {
                const move = moves[i];
                this._board.movePiece(move.from, move.to, true).then(() => {
                    if (i === sanMoves.length - 2) {
                        this._board.addMarker(MARKER_TYPE.bevel, moves[i + 1].from);
                    }
                    if (i === sanMoves.length - 1) {
                        this._board.addMarker(MARKER_TYPE.bevel, move.to);
                    }
                });
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
                        this._board.movePiece(from, to, true);
                        break;
                    case move.flags.includes('e'):
                        this._board.setPiece(move.to.substring(0, 1) + move.from.substring(1), null);
                        break;
                    case move.flags.includes('p'):
                        this._board.setPiece(move.to, move.color + move.promotion);
                        break;
                }
            }
            return moves.slice(-1)[0];
        }
        return false;
    }

    _undoMove(repeat = 1) {

        let move;

        for (let i = repeat; i > 0; i--) {

            move = this._chess.currentMove();

            if (move) {

                /**
                 * Play chess move
                 */
                this._chess.seek(move.previous);

                /**
                 * Play board move
                 */
                if (i === repeat) {
                    this._board.removeMarkers();
                }
                this._board.movePiece(move.to, move.from, true).then(() => {
                    if (move.previous && i === 1) {
                        this._board.addMarker(MARKER_TYPE.bevel, move.previous.from);
                        this._board.addMarker(MARKER_TYPE.bevel, move.previous.to);
                    }
                });
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
                        this._board.movePiece(to, from, true);
                        break;
                    case move.flags.includes('e'):
                        this._board.setPiece(move.to.substring(0, 1) + move.from.substring(1), move.color + 'p');
                        break;
                    case move.flags.includes('p'):
                        this._board.setPiece(move.from, move.color + 'p');
                        break;
                }

                if (move.flags.includes('c')) {
                    this._board.setPiece(move.to, (move.color === 'w' ? 'b' : 'w') + move.captured);
                }
            }
        }

        return move;
    }

    /**
     * @param {movePlayedCallback} moveHandler
     * @param {'analysis' | 'variation'} mode
     * @param {number | true | false} autoNext
     */
    enablePlayableMove(moveHandler, autoNext = false) {

        this.#movePlayedCallback = moveHandler;

        this._autoNext = autoNext;

        this._enableMoveInput();
    }

    disablePlayableMove() {
        this.#movePlayedCallback = null;
        this._autoNext = false;
        this._board.disableMoveInput();
    }

    previousMove() {
        const move = this._undoMove();
        if (move) {
            this._board.disableMoveInput();
            return true;
        }
        return false;
    }

    nextMove() {
        const move = this._chess.nextMove();
        if (move) {
            return this._playMoves(move.san);
        }
    }

    gotoEnd() {
        const index = this._chess.history().length;
        this.gotoMove(index);
        return index;
    }

    gotoMove(index) {

        if (index < 0) {
            throw new Error("Index should be greater than or equal to 0");
        }

        const currentMove = this._chess.currentMove();
        const currentIndex = currentMove ? currentMove.ply : 0;

        switch (true) {
            case index > currentIndex:
                const sanMoves = [];
                let nextMove = this._chess.nextMove();
                sanMoves.push(nextMove.san);
                while (nextMove && nextMove.ply < index) {
                    nextMove = nextMove.next;
                    sanMoves.push(nextMove.san);
                }
                this._playMoves(sanMoves);
                break;
            case index < currentIndex:
                this._undoMove(currentIndex - index);
                break;
            default:
                return false;
        }
        return true;
    }

    addArrow(lan) {
        this._board.addArrow(ARROW_TYPE.pointy, lan.substring(0, 2), lan.substring(2, 4))
    }

    removeArrows() {
        this._board.removeArrows();
    }

    getPGNMoves() {
        return this._chess.renderLine(this._chess.currentMove(), { skipHeader: true });
    }
}