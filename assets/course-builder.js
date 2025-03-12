
import { FEN } from 'cm-chessboard'

import { AnalysisChessboardEngine } from './chessboardengine/analysis-cbe.js';

let fen = FEN.start;
fen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq -';

/**
 * @type AnalysisChessboardEngine board
 */
let board;

/**
 * @type array percentages
 */
let percentages = [];

let missingPercentages = 0;

let formSubmitting = null;
let buildingFormLinks = null;
let form = null, saveForm;
let previousButton = null, saveButton = null;

let whole = true;

let stream_count;

function build_move(event) {
    console.log('build_move');

    board.removeArrows();
    board.playMoves(event.currentTarget.getAttribute('data-san'));

    console.log('end build_move');
}

function build_moves_previous(event) {
    console.log('build_moves_previous');

    board.removeArrows();
    board.undoMove();

    console.log('end build_moves_previous');
}

function mouse_enter(event) {
    board.addArrow(event.currentTarget.getAttribute('data-lan'));
}

function mouse_leave() {
    board.removeArrows();
}

function on_move_played(event) {
    console.log(event);
    if (board.getLanMoves()) {
        previousButton.classList.remove('disabled');
    } else {
        previousButton.classList.add('disabled');
    }
    if (typeof event.undo === 'undefined' || !event.undo) {
        let moveElement = document.querySelector('.build_moves[data-lan="' + event.lan + '"]');
        let percentage = moveElement?.getAttribute('data-expected');
        if (missingPercentages > 0 || percentage == null) {
            missingPercentages++;
        } else {
            percentages.push(percentage);
        }
    } else {
        if (missingPercentages > 0) {
            missingPercentages--;
        } else {
            percentages.pop();
        }
    }
    build_moves();
}

function build_moves() {
    let movesInput = document.getElementById('build_moves_form_lanMoves');
    movesInput.value = board.getLanMoves();

    console.log(percentages);
    console.log(missingPercentages);

    let percentageInput = document.getElementById('build_moves_form_expectedPercentage');
    percentageInput.value = percentages.length > 0 ? percentages[percentages.length - 1] : 1;

    let missingPercentagesInput = document.getElementById('build_moves_form_missingPercentages');
    missingPercentagesInput.value = missingPercentages;

    form.requestSubmit();
}

function save_moves() {
    let movesInput = document.getElementById('save_moves_form_lanMoves');
    movesInput.value = board.getLanMoves();
    saveForm.requestSubmit();
}

document.addEventListener('DOMContentLoaded', function () {

    let course = JSON.parse(document.querySelector('.js-course').getAttribute('data-course'));

    // TODO get fen from course and check if ' 0 1' needed
    board = new AnalysisChessboardEngine(document.getElementById('board'), course.blackOrientation ? 'b' : 'w', null, [], fen + ' 0 1');

    form = document.querySelector('form[name="build_moves_form"]');
    saveForm = document.querySelector('form[name="save_moves_form"]');

    previousButton = document.getElementById('build_moves_previous');
    previousButton.addEventListener('click', build_moves_previous);

    saveButton = document.getElementById('save_moves');
    saveButton.addEventListener('click', save_moves);

    board.enablePlayableMove(on_move_played, true);

    build_moves();

    document.addEventListener('turbo:submit-start', (event) => {
        console.log('submit-start');
        formSubmitting = event.detail.formSubmission;

        let formName = formSubmitting.formElement.getAttribute('name');

        if (formName === 'save_moves_form') {
            board.disablePlayableMove();
            previousButton.classList.add('disabled');
        }

        saveButton.classList.add('disabled');

        if (buildingFormLinks !== null) {
            for (let i = 0; i < buildingFormLinks.length; i++) {
                buildingFormLinks[i].removeEventListener('click', build_move);
            }
        }

        whole = true;
    });

    document.addEventListener('turbo:submit-end', (event) => {
        console.log('submit-end');
        stream_count = 0;
    });

    document.addEventListener('turbo:before-stream-render', (event) => {
        console.log('before-stream-render');
        stream_count++;

        let formName = formSubmitting.formElement.getAttribute('name');

        if (formName === 'build_moves_form' || stream_count > 1) {

            if (buildingFormLinks !== null) {
                for (let i = 0; i < buildingFormLinks.length; i++) {
                    buildingFormLinks[i].removeEventListener('mouseenter', mouse_enter);
                    buildingFormLinks[i].removeEventListener('mouseleave', mouse_leave);
                }
                buildingFormLinks = null;
            }
        }
    });

    document.addEventListener('turbo:after-stream-render', (event) => {
        console.log('after-stream-render');
        stream_count--;

        if (stream_count < 1) {

            let formName = formSubmitting.formElement.getAttribute('name');

            if (formName === 'build_moves_form') {
                buildingFormLinks = document.querySelectorAll('a.build_moves');
                whole = JSON.parse(document.getElementById('build_moves').getAttribute('data-moves-whole'));
                if (JSON.parse(document.getElementById('build_moves').getAttribute('data-can-save'))) {
                    saveButton.classList.remove('disabled');
                }
                if (missingPercentages > 0) {
                    let percentages = JSON.parse(document.getElementById('build_moves').getAttribute('data-missing-percentages'));
                    if (percentages.length === missingPercentages) {
                        percentages.forEach(percentage => {
                            if (typeof percentage !== 'undefined' && percentage !== null) {
                                missingPercentages--;
                                percentages.push(percentage);
                            }
                        });
                    }
                }
                if (buildingFormLinks !== null) {
                    for (let i = 0; i < buildingFormLinks.length; i++) {
                        buildingFormLinks[i].addEventListener('mouseenter', mouse_enter);
                        buildingFormLinks[i].addEventListener('mouseleave', mouse_leave);
                    }
                }
            }

            if (formName === 'save_moves_form') {
                board.enablePlayableMove(on_move_played, true);
                previousButton.classList.remove('disabled');
            }

            if (buildingFormLinks !== null) {
                for (let i = 0; i < buildingFormLinks.length; i++) {
                    buildingFormLinks[i].addEventListener('click', build_move);
                }
            }

            formSubmitting = null;
        }
    });


    /**
     * Mercure
     */
    const url = new URL(JSON.parse(document.getElementById('mercure-url').textContent));
    const env_url = new URL(document.getElementById('mercure-url').getAttribute('data-mercure-url'));

    console.log(url.href);

    let eventSource;
    if (url.origin !== env_url.origin || url.pathname !== env_url.pathname) {
        eventSource = new EventSource(url.href);
    }

    if (typeof eventSource !== 'undefined') {
        eventSource.onmessage = event => {
            if (!whole) {
                let data = JSON.parse(event.data);
                console.log(data);
                if (data.courseId === course.id && data.baseFen === fen && data.lanMoves === board.getLanMoves()) {
                    build_moves();
                    whole = true;
                }
            }
        }
    }
});