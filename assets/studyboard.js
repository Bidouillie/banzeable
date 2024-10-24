
import { ChessboardEngine } from './chessboardengine/chessboardengine.js'
import { ToastMaker } from './toast.js';

// ChessboardEngine
let board;

let moveIndex = 0;

function updateMoveIndex(newMoveIndex) {

    moveIndex = newMoveIndex;

    const movesEl = document.getElementById('moves');

    const activeButtons = movesEl.querySelectorAll('button.active');
    if (activeButtons.length > 0) {
        activeButtons[0].blur();
        activeButtons[0].classList.remove('active');
    }

    const buttons = movesEl.querySelectorAll('[data-move-index="' + moveIndex.toString() + '"]');
    if (buttons.length > 0) {
        buttons[0].classList.add('active');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const variation = JSON.parse(document.querySelector('.js-variation').getAttribute('data-variation'));
    console.log(variation);

    board = new ChessboardEngine(document.getElementById('board'), variation.moves.map((move) => {
        return move.notation.text;
    }), variation.blackOrientation ? 'b' : 'w');

    let movesDone = 0, totalMoves = Math.floor((variation.moves.length + (variation.blackOrientation ? 0 : 1)) / 2);

    const progressBar = document.getElementById('progressBar');

    const movesEl = document.getElementById('moves');

    movesEl.addEventListener('click', (event) => {
        if (event.target.tagName === 'BUTTON') {
            if (![...event.target.classList].includes('active')) {
                const activeButtons = movesEl.querySelectorAll('button.active');
                if (activeButtons.length > 0) {
                    activeButtons[0].classList.remove('active');
                }
                updateMoveIndex(Number(event.target.getAttribute('data-move-index')));
                board.gotoMove(moveIndex);
            }
        }
    })

    board.enablePlayableMove((event) => {
        console.log(event);

        if (!event.hasOwnProperty('moveValidation') || event.moveValidation) {

            if (event.hasOwnProperty('moveValidation')) {

                for (let i = progressBar.classList.length - 1; i >= 0; i--) {
                    const className = progressBar.classList[i];
                    if (className.startsWith('bg-')) {
                        progressBar.classList.remove(className);
                    }
                }

                movesDone++;

                if (event.lastMove) {
                    ToastMaker.toast("Good move, variation is over!", 'success', 3000);
                    progressBar.classList.remove('progress-bar-animated');
                    progressBar.classList.remove('progress-bar-striped');
                    progressBar.classList.add('bg-success');
                } else {
                    ToastMaker.toast("Good move", 'success');
                }
                progressBar.style.width = Math.floor(movesDone * 100 / totalMoves).toString() + "%";
            }

            updateMoveIndex(event.index);
        } else {
            ToastMaker.toast("Wrong move", 'danger');
        }
    }, 1000, 'variation');

    document.addEventListener('keydown', (event) => {
        switch (event.key) {
            case 'ArrowLeft':
                if (board.previousMove()) {
                    updateMoveIndex(moveIndex - 1);
                }
                break;
            case 'ArrowRight':
                if (board.nextMove()) {
                    updateMoveIndex(moveIndex + 1);
                }
                break;
        }
    });
});