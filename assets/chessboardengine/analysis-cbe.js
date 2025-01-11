
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

        let event = {};

        this.#switchTurn();

        this._fireMoveEvent(event);
    }

    playMoves(sanMoves) {
        if (this._playMoves(sanMoves)) {
            this._fireMoveEvent();
            this.#switchTurn();
        }
        return false;
    }
}