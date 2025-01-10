
import { ChessboardEngine } from "./chessboardengine.js";

export class VariationChessboardEngine extends ChessboardEngine {

    #halfMovesProgress = 0;

    #validateOrUndoMove(move) {

        if (this._autoNext !== false) {
            this._board.disableMoveInput();
        }

        if (this._chess.isMainline(move, move.previous)) {
            this.#halfMovesProgress++;
            if (this._autoNext !== false) {
                setTimeout(() => {
                    const move = this._chess.nextMove();
                    if (move && this._playMove(move.san)) {
                        this.#halfMovesProgress++;
                        this._fireMoveEvent();
                        this._enableMoveInput();
                    }
                }, this._autoNext);
            }
            return true;
        }

        // TODO stop moves to be sure that the last move played is the incorrect one
        if (this._autoNext !== false) {
            setTimeout(() => {
                if (this._undoMove()) {
                    this._fireMoveEvent();
                    this._enableMoveInput();
                }
            }, this._autoNext);
        }

        return false;
    }

    _enableMoveInput() {
        this._board.enableMoveInput((event) => {
            return this._inputHandler(event);
        }, this._board.getOrientation());
    }

    _moveInputFinished() {

        const move = this._chess.currentMove();

        const moveValidation = this.#validateOrUndoMove(move);

        this._fireMoveEvent({ moveValidation, 'lastMove': moveValidation && move.ply === this._chess.history().length });
    }

    enablePlayableMove(moveHandler, autoNext = false) {
        super.enablePlayableMove(moveHandler, autoNext);
        if (this.orientation !== this._chess.turn()) {
            if (this._playMove(this._chess.nextMove().san)) {
                this.#halfMovesProgress++;
                this._fireMoveEvent();
            }
        }
    }

    nextMove() {
        let movePlayed = super.nextMove();

        if (movePlayed && movePlayed.ply === this.#halfMovesProgress) {
            this._enableMoveInput();
        } else {
            this._board.disableMoveInput();
        }
    }

    gotoEnd() {
        const index = this.#halfMovesProgress;
        this.gotoMove(index);
        return index;
    }

    gotoMove(index) {

        let moved = super.gotoMove(index);
        if (moved) {
            if (index === this.#halfMovesProgress) {
                this._enableMoveInput();
            } else {
                this._board.disableMoveInput();
            }
        }
        return moved;
    }
}