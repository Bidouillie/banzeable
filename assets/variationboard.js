
import './vendor/cm-chessboard/assets/chessboard.css';
import './vendor/cm-chessboard/assets/extensions/markers/markers.css';

import { Chessboard } from 'cm-chessboard'
import { Markers } from 'cm-chessboard/src/extensions/markers/Markers.js';
import { Chess } from 'chess.js';

const chess = new Chess();
let board;

document.addEventListener('DOMContentLoaded', function () {
    const variation = JSON.parse(document.querySelector('.js-variation').getAttribute('data-variation'));
    console.log(variation);

    board = new Chessboard(document.getElementById('board'), {
        position: variation.FEN,
        assetsUrl: '/cm-chessboard/assets/', // wherever you copied the assets folder to, could also be in the node_modules folder
        extensions: [{ class: Markers }],
        style: {
            pieces: {
                file: '/cm-chessboard/assets/pieces/staunty.svg',
            }
        }
    });

    chess.load(variation.FEN);

    for (let move in variation.moves) {
        playMove(variation.moves[move].notation.text);
    }
});

function playMove(notation) {
    const move = chess.move(notation);

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