
import { FEN } from 'cm-chessboard'

import { ChessboardEngine } from './chessboardengine/chessboardengine.js'

let fen = FEN.start;

let board;

document.addEventListener('DOMContentLoaded', function () {

    let course = JSON.parse(document.querySelector('.js-course').getAttribute('data-course'));

    console.log(course);

    board = new ChessboardEngine(document.getElementById('board'), course.blackOrientation ? 'b' : 'w', null, [], fen);

    board.enablePlayableMove((event) => {
        console.log(event);
    });

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