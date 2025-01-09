
import { INPUT_EVENT_TYPE } from 'cm-chessboard'

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

    _inputHandler(event) {

        switch (event.type) {
            case INPUT_EVENT_TYPE.moveInputStarted:
                return true;
            case INPUT_EVENT_TYPE.validateMoveInput:
                return this._validateMoveInput(event);
            case INPUT_EVENT_TYPE.moveInputFinished:
                if (event.legalMove) {
                    this._moveInputFinished();
                }
                break;
        }
    }

    playMove(sanMove) {
        if (this._playMove(sanMove)) {
            this._fireMoveEvent();
            this.#switchTurn();
        }
        return false;
    }
}