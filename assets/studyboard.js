
import { ChessboardEngine } from './chessboardengine/chessboardengine.js'
import { ToastMaker } from './toast.js';

// ChessboardEngine
let board;

document.addEventListener('DOMContentLoaded', function () {
    const variation = JSON.parse(document.querySelector('.js-variation').getAttribute('data-variation'));
    console.log(variation);

    board = new ChessboardEngine(document.getElementById('board'), variation.moves.map((move) => {
        return move.notation.text;
    }), variation.blackOrientation ? 'b' : 'w');

    let movesDone = 0, totalMoves = Math.floor((variation.moves.length + (variation.blackOrientation ? 0 : 1)) / 2);

    const progressBar = document.getElementById('progressBar');

    board.enablePlayableMove((event) => {
        if (event.moveValidation) {

            movesDone++;
            for (let i = progressBar.classList.length - 1; i >= 0; i--) {
                const className = progressBar.classList[i];
                if (className.startsWith('bg-')) {
                    progressBar.classList.remove(className);
                }
            }
            if (event.lastMove) {
                ToastMaker.toast("Good move, variation is over!", 'success', 3000);
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.remove('progress-bar-striped');
                progressBar.classList.add('bg-success');
            } else {
                ToastMaker.toast("Good move", 'success');
            }
            progressBar.style.width = Math.floor(movesDone * 100 / totalMoves).toString() + "%";
        } else {
            ToastMaker.toast("Wrong move", 'danger');
        }
    }, 1000, 'variation');

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