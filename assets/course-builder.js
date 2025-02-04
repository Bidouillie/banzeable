
import { FEN } from 'cm-chessboard'

import { AnalysisChessboardEngine } from './chessboardengine/analysis-cbe.js';

let fen = FEN.start;

/**
 * @type AnalysisChessboardEngine board
 */
let board;

let formSubmitting = null;
let buildingFormLinks = null;
let form = null
let previousForm = null;

function build_move(event) {
    console.log('build_move');

    board.removeArrows();
    board.playMoves(event.currentTarget.getAttribute('data-san'));

    console.log('end build_move');
}

function build_previous_move(event) {
    console.log('build_previous_move');

    board.removeArrows();
    board.undoMove();

    console.log('end build_previous_move');
}

function mouse_enter(event) {
    board.addArrow(event.currentTarget.getAttribute('data-lan'));
}

function mouse_leave() {
    board.removeArrows();
}

function on_move_played(event) {
    console.log(event);
    if (!event.undo) {
        let moves = form.querySelector('#build_moves_moves').querySelectorAll('input');
        moves[moves.length - 1].value = event.lan;
        form.requestSubmit();
    }
}

document.addEventListener('DOMContentLoaded', function () {

    let course = JSON.parse(document.querySelector('.js-course').getAttribute('data-course'));

    board = new AnalysisChessboardEngine(document.getElementById('board'), course.blackOrientation ? 'b' : 'w', null, [], fen);

    document.querySelector('form[name="build_moves"]').requestSubmit();

    document.addEventListener('turbo:submit-start', (event) => {
        console.log('submit-start');
        formSubmitting = event.detail.formSubmission;

        let formName = formSubmitting.formElement.getAttribute('name');
        if (buildingFormLinks !== null) {
            for (let i = 0; i < buildingFormLinks.length; i++) {
                buildingFormLinks[i].removeEventListener('click', build_move);
                if (formName.startsWith('build_move')) {
                    buildingFormLinks[i].removeEventListener('mouseenter', mouse_enter);
                    buildingFormLinks[i].removeEventListener('mouseleave', mouse_leave);
                }
            }
        }

        if (previousForm !== null) {
            previousForm.removeEventListener('submit', build_previous_move);
        }

        if (formSubmitting.formElement.id === 'save-moves') {
            board.disablePlayableMove();
        }
    });
    document.addEventListener('turbo:submit-end', (event) => {
        console.log('submit-end');
    });

    document.addEventListener('turbo:before-stream-render', (event) => {
        console.log('before-stream-render');
    });
    document.addEventListener('turbo:after-stream-render', (event) => {
        console.log('after-stream-render');
        let formName = formSubmitting.formElement.getAttribute('name');

        if (formName.startsWith('build_move')) {
            buildingFormLinks = document.querySelectorAll('a.build_moves');

            form = document.getElementById('build_moves');

            previousForm = document.getElementById('build_moves_previous');

            let save_variation_PGN_field = document.getElementById('move_builder_variation_variation_PGN');
            if (save_variation_PGN_field !== null) {
                save_variation_PGN_field.value = board.getPGNMoves();
            }
        }

        if (buildingFormLinks !== null) {
            for (let i = 0; i < buildingFormLinks.length; i++) {
                buildingFormLinks[i].addEventListener('click', build_move);
                if (formName.startsWith('build_move')) {
                    buildingFormLinks[i].addEventListener('mouseenter', mouse_enter);
                    buildingFormLinks[i].addEventListener('mouseleave', mouse_leave);
                }
            }
        }

        if (previousForm !== null) {
            previousForm.addEventListener('submit', build_previous_move);
        }

        board.enablePlayableMove(on_move_played);
        formSubmitting = null;
    });
});