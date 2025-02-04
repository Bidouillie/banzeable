
import { ChessboardEngine } from "./chessboardengine.js";

export class AnalysisChessboardEngine extends ChessboardEngine {

    #switchTurn() {
        this._board.disableMoveInput();
        if (this._autoNext === true) {
            this._board.enableMoveInput((event) => {
                return this._inputHandler(event);
            }, this._chess.turn());
        } else {

            if (this._autoNext !== false) {
                setTimeout(() => {
                    this._board.enableMoveInput((event) => {
                        return this._inputHandler(event);
                    }, this._chess.turn());
                }, this._autoNext);
            }
        }
    }

    _moveInputFinished() {

        this.#switchTurn();

        this._fireMoveEvent();
    }

    playMoves(sanMoves) {
        if (this._playMoves(sanMoves)) {
            this._fireMoveEvent();
            this.#switchTurn();
        }
    }

    undoMove() {
        let move = this._undoMove();
        if (move) {
            this._fireMoveEvent({ undo: true, lan: move.lan, san: move.san });
            this.#switchTurn();
        }
    }
}