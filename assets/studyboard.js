
import { VariationChessboardEngine } from './chessboardengine/variation-cbe.js';
import { ToastMaker } from './toast.js';

// VariationChessboardEngine
let board;

let variation;

let mode = 'review';

let moveIndex = 0, studyProgress = 0;

let movesEl, progressBarEl, navigationEl;

function previousMove() {
    if (board.previousMove()) {
        updateMoveIndex(moveIndex - 1);
    }
}

function nextMove() {
    if (moveIndex <= studyProgress * 2 - (variation.blackOrientation ? 0 : 1)) {
        if (board.nextMove()) {
            updateMoveIndex(moveIndex + 1);
        }
    }
}

function updateMoveIndex(newMoveIndex) {

    moveIndex = newMoveIndex;

    const activeButtonList = movesEl.querySelectorAll('button.active');
    if (activeButtonList.length > 0) {
        activeButtonList[0].blur();
        activeButtonList[0].classList.remove('active');
    }

    const moveCountList = movesEl.querySelectorAll('[data-move-index="' + Math.ceil(moveIndex / 2).toString() + '"]');
    if (moveCountList.length > 0) {
        moveCountList[0].classList.remove('d-none');
    }

    const moveButtonList = movesEl.querySelectorAll('[data-half-move-index="' + moveIndex.toString() + '"]');
    if (moveButtonList.length > 0) {
        moveButtonList[0].classList.remove('d-none');
        moveButtonList[0].classList.add('active');
    }
}

document.addEventListener('DOMContentLoaded', function () {

    movesEl = document.getElementById('moves');
    progressBarEl = document.getElementById('progressBar');
    navigationEl = document.getElementById('navigation');

    variation = JSON.parse(document.querySelector('.js-variation').getAttribute('data-variation'));
    console.log(variation);

    board = new VariationChessboardEngine(document.getElementById('board'), variation.blackOrientation ? 'b' : 'w', null, variation.moves.map((move) => move.notation.text));

    let totalMoves = Math.floor((variation.moves.length + (variation.blackOrientation ? 0 : 1)) / 2);

    movesEl.addEventListener('click', (event) => {
        if (event.target.tagName === 'BUTTON') {
            if (![...event.target.classList].includes('active')) {
                const activeButtons = movesEl.querySelectorAll('button.active');
                if (activeButtons.length > 0) {
                    activeButtons[0].classList.remove('active');
                }
                updateMoveIndex(Number(event.target.getAttribute('data-half-move-index')));
                board.gotoMove(moveIndex);
            }
        }
    })

    board.enablePlayableMove((event) => {
        console.log(event);

        if (!event.hasOwnProperty('moveValidation') || event.moveValidation) {

            if (event.hasOwnProperty('moveValidation')) {

                for (let i = progressBarEl.classList.length - 1; i >= 0; i--) {
                    const className = progressBarEl.classList[i];
                    if (className.startsWith('bg-')) {
                        progressBarEl.classList.remove(className);
                    }
                }

                studyProgress++;

                if (event.lastMove) {
                    ToastMaker.toast("Good move, variation is over!", 'success', 3000);
                    progressBarEl.classList.remove('progress-bar-animated');
                    progressBarEl.classList.remove('progress-bar-striped');
                    progressBarEl.classList.add('bg-success');
                } else {
                    ToastMaker.toast("Good move", 'success');
                }
                progressBarEl.style.width = Math.floor(studyProgress * 100 / totalMoves).toString() + "%";
            }

            updateMoveIndex(event.index);
        } else {
            ToastMaker.toast("Wrong move", 'danger');
        }
    }, 1000);

    navigationEl.querySelectorAll('button[data-action="start"]')[0].addEventListener('click', () => {
        if (board.gotoMove(0)) {
            updateMoveIndex(0);
        }
    });

    navigationEl.querySelectorAll('button[data-action="previous"]')[0].addEventListener('click', () => {
        previousMove();
    });

    navigationEl.querySelectorAll('button[data-action="next"]')[0].addEventListener('click', () => {
        nextMove();
    });

    navigationEl.querySelectorAll('button[data-action="end"]')[0].addEventListener('click', () => {
        updateMoveIndex(board.gotoEnd());
    });

    document.addEventListener('keydown', (event) => {
        switch (event.key) {
            case 'ArrowLeft':
                previousMove();
                break;
            case 'ArrowRight':
                nextMove();
                break;
        }
    });
});