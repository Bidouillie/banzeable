
import { FEN } from 'cm-chessboard'

import { ChessboardEngine } from './chessboardengine/chessboardengine.js'

let fen = FEN.start;
fen = 'rnbqk2r/p1p1bppp/4pn2/1pPP4/Q7/8/PP1P1PPP/RNB1KBNR w KQkq b6 0 6';


let board;

document.addEventListener('DOMContentLoaded', function () {

    board = new ChessboardEngine(document.getElementById('board'), [], 'w', fen);

    board.enablePlayableMove();

    /*
    document.addEventListener('keydown', (event) => {
        switch (event.key) {
            case 'ArrowLeft':
                board.previousMove();
                break;
            case 'ArrowRight':
                board.nextMove();
                break;
        }
    });
    */

});