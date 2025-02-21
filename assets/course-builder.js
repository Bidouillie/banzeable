
import { FEN } from 'cm-chessboard'

import { AnalysisChessboardEngine } from './chessboardengine/analysis-cbe.js';

let fen = FEN.start;
fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq -';

/**
 * @type AnalysisChessboardEngine board
 */
let board;

let formSubmitting = null;
let buildingFormLinks = null;
let form = null
let reloadForm = null;
let previousForm = null;

let whole = true;

function build_move(event) {
    console.log('build_move');

    board.removeArrows();
    board.playMoves(event.currentTarget.getAttribute('data-san'));

    console.log('end build_move');
}

function build_move_reload(event) {
    console.log('build_move_reload');

    board.removeArrows();
    board.disablePlayableMove();

    console.log('end build_move_reload');
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

    // TODO get fen from course and check if ' 0 1' needed
    board = new AnalysisChessboardEngine(document.getElementById('board'), course.blackOrientation ? 'b' : 'w', null, [], fen + ' 0 1');

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
            buildingFormLinks = null;
        }

        if (reloadForm !== null) {
            reloadForm.removeEventListener('submit', build_move_reload);
            reloadForm = null;
            whole = true;
        }

        if (previousForm !== null) {
            previousForm.removeEventListener('submit', build_previous_move);
            previousForm = null;
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

            form = document.getElementById('build_moves_form');

            reloadForm = document.getElementById('build_moves_reload');
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

        if (reloadForm !== null) {
            whole = JSON.parse(document.getElementById('build_moves').getAttribute('data-moves-whole'));
            reloadForm.addEventListener('submit', build_move_reload);
        }

        if (previousForm !== null) {
            previousForm.addEventListener('submit', build_previous_move);
        }

        board.enablePlayableMove(on_move_played);
        formSubmitting = null;
    });

    const url = JSON.parse(document.getElementById('mercure-url').textContent);
    console.log(url);
    const eventSource = new EventSource(url);
    eventSource.onmessage = event => {
        if (!whole) {
            let data = JSON.parse(event.data);
            console.log(data);
            if (data.courseId === course.id && data.baseFen === fen && data.lanMoves === board.getLanMoves()) {
                reloadForm.requestSubmit();
                whole = true;
            }
        }
    }
});