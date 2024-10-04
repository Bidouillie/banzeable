
import './vendor/cm-chessboard/assets/chessboard.css';
import './vendor/cm-chessboard/assets/extensions/markers/markers.css';

import { Chessboard } from 'cm-chessboard'
import { Markers } from 'cm-chessboard/src/extensions/markers/Markers.js';
import { Chess } from 'chess.js';

const chess = new Chess();
let board;

// moves to be played
let moves = [];

document.addEventListener('DOMContentLoaded', function () {
    const variation = JSON.parse(document.querySelector('.js-variation').getAttribute('data-variation'));
    console.log(variation);

    board = new Chessboard(document.getElementById('board'), {
        position: variation.FEN,
        assetsUrl: '/cm-chessboard/assets/',
        extensions: [{ class: Markers }],
        style: {
            pieces: {
                file: '/cm-chessboard/assets/pieces/staunty.svg',
            }
        }
    });

    chess.load(variation.FEN);

    for (let moveEntity in variation.moves) {
        playMove(variation.moves[moveEntity].notation.text, true);
    }

    moves = chess.history().slice().reverse();

    chess.reset();

    document.addEventListener('keydown', keyPressed);
});

function keyPressed(e) {
    switch (e.key) {
        case 'ArrowLeft':
            undoMove();
            break;
        case 'ArrowRight':
            redoMove();
            break;
    }
}

function playMove(notation, chessOnly = false) {

    /**
     * Play chess move
     */
    const move = chess.move(notation);

    if (!chessOnly) {
        /**
         * Play board move
         */
        board.movePiece(move.from, move.to);
        switch (true) {
            case move.flags.includes('k'):
            case move.flags.includes('q'):
                let from;
                let to;
                if (move.flags.includes('k')) {
                    from = 'h' + move.from.substring(1);
                    to = 'f' + move.to.substring(1);
                } else {
                    from = 'a' + move.from.substring(1);
                    to = 'd' + move.to.substring(1);
                }
                board.movePiece(from, to);
                break;
            case move.flags.includes('e'):
                board.setPiece(move.to.substring(0, 1) + move.from.substring(1), null);
                break;
            case move.flags.includes('p'):
                board.setPiece(move.to, move.color + move.promotion);
                break;
        }
    }
}

function undoMove() {

    /**
     * Play chess move
     */
    const move = chess.undo();

    if (move) {

        /**
         * Play board move
         */
        board.movePiece(move.to, move.from);
        switch (true) {
            case move.flags.includes('k'):
            case move.flags.includes('q'):
                let from;
                let to;
                if (move.flags.includes('k')) {
                    from = 'h' + move.from.substring(1);
                    to = 'f' + move.to.substring(1);
                } else {
                    from = 'a' + move.from.substring(1);
                    to = 'd' + move.to.substring(1);
                }
                board.movePiece(to, from);
                break;
            case move.flags.includes('e'):
                board.setPiece(move.to.substring(0, 1) + move.from.substring(1), move.color + 'p');
                break;
            case move.flags.includes('p'):
                board.setPiece(move.from, move.color + 'p');
                break;
        }

        if (move.flags.includes('c')) {
            board.setPiece(move.to, (move.color === 'w' ? 'b' : 'w') + move.captured);
        }
        
        moves.push(move);
    }
}

function redoMove() {
    const move = moves.pop();
    if (move) {
        playMove(move);
    }
}