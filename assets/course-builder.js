
import { FEN } from 'cm-chessboard'

import { AnalysisChessboardEngine } from './chessboardengine/analysis-cbe.js';

let fen = FEN.start;
let board;

let clicking = false;

let formSubmitting = null;
let buildingFormLinks = null;

function build_move(event) {
    console.log('build_move');
    if (!clicking) {
        clicking = true;
        board.removeArrows();
        board.playMoves(event.currentTarget.getAttribute('data-san'));
        console.log('end build_move');
    }
}

function mouse_enter(event) {
    if (!clicking) {
        board.addArrow(event.currentTarget.getAttribute('data-lan'));
    }
}

function mouse_leave() {
    if (!clicking) {
        board.removeArrows();
    }
}

function on_move_played(event) {
    console.log(event);
    clicking = true;
    for (let i = 0; i < buildingFormLinks.length; i++) {
        if (buildingFormLinks[i].getAttribute('data-san') === event.san) {
            buildingFormLinks[i].querySelector('form').requestSubmit();
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {

    let course = JSON.parse(document.querySelector('.js-course').getAttribute('data-course'));

    board = new AnalysisChessboardEngine(document.getElementById('board'), course.blackOrientation ? 'b' : 'w', null, [], fen);

    document.addEventListener('turbo:submit-start', (event) => {
        console.log('submit-start');
        formSubmitting = event.detail.formSubmission;
    });
    document.addEventListener('turbo:submit-end', (event) => {
        console.log('submit-end');
    });

    document.addEventListener('turbo:before-stream-render', (event) => {
        console.log('before-stream-render');
        if (buildingFormLinks !== null) {
            for (let i = 0; i < buildingFormLinks.length; i++) {
                buildingFormLinks[i].removeEventListener('click', build_move);
                buildingFormLinks[i].removeEventListener('mouseenter', mouse_enter);
                buildingFormLinks[i].removeEventListener('mouseleave', mouse_leave);
            }
            clicking = false;
        }
    });
    document.addEventListener('turbo:after-stream-render', (event) => {
        console.log('after-stream-render');
        if (formSubmitting.formElement.getAttribute('name').startsWith('build_move')) {
            buildingFormLinks = document.querySelectorAll('a.build_move');
            for (let i = 0; i < buildingFormLinks.length; i++) {
                buildingFormLinks[i].addEventListener('click', build_move);
                buildingFormLinks[i].addEventListener('mouseenter', mouse_enter);
                buildingFormLinks[i].addEventListener('mouseleave', mouse_leave);
            }

            board.enablePlayableMove(on_move_played);
        }
        formSubmitting = null;
    });
});