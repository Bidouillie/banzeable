
import { FEN } from 'cm-chessboard'

import { ChessboardEngine } from './chessboardengine/chessboardengine.js'

let fen = FEN.start;
let board;

let fromBoard;

let formSubmitting = null;
let buildingForms = null;

function build_move(event) {
    if (typeof fromBoard === 'undefined' || !fromBoard) {
        board.playMove(event.target.getAttribute('data-san'));
    }
    for (let i = 0; i < buildingForms.length; i++) {
        let submitButtons = buildingForms[i].querySelectorAll('button[type="submit"]');
        for (let j = 0; j < submitButtons.length; j++) {
            submitButtons[j].setAttribute('disabled', true);
        }
    }
}

function click_move(event) {
    console.log(event);
    fromBoard = true;
    for (let i = 0; i < buildingForms.length; i++) {
        if (buildingForms[i].getAttribute('data-san') === event.san) {
            buildingForms[i].querySelector('button[type="submit"]').click();
        }
    }
    fromBoard = false;
}

document.addEventListener('DOMContentLoaded', function () {

    let course = JSON.parse(document.querySelector('.js-course').getAttribute('data-course'));

    console.log(course);

    document.addEventListener('turbo:submit-start', (event) => {
        formSubmitting = event.detail.formSubmission;
    });
    document.addEventListener('turbo:submit-end', (event) => {
    });

    document.addEventListener('turbo:before-stream-render', (event) => {
        if (formSubmitting !== null) {
            if (formSubmitting.formElement.id === 'course_build_start') {
                board = new ChessboardEngine(document.getElementById('board'), course.blackOrientation ? 'b' : 'w', null, [], fen);
            }
            if (formSubmitting.formElement.getAttribute('name').startsWith('build_move')) {
                if (buildingForms !== null) {
                    for (let i = 0; i < buildingForms.length; i++) {
                        buildingForms[i].removeEventListener('submit', build_move);
                    }
                }
            }
        }
    });
    document.addEventListener('turbo:after-stream-render', (event) => {
        if (formSubmitting !== null) {
            if (formSubmitting.formElement.getAttribute('name').startsWith('build_move')) {
                buildingForms = document.querySelectorAll('form[name^=build_move]');
                for (let i = 0; i < buildingForms.length; i++) {
                    buildingForms[i].addEventListener('submit', build_move);
                }

                board.enablePlayableMove(click_move, 'analysis');
            }
            formSubmitting = null;
        }
    });
});