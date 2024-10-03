
import './vendor/cm-chessboard/assets/chessboard.css';
import './vendor/cm-chessboard/assets/extensions/markers/markers.css';

import { Chessboard, COLOR, FEN, INPUT_EVENT_TYPE } from 'cm-chessboard'
import { Markers } from 'cm-chessboard/src/extensions/markers/Markers.js';
import { Chess } from 'chess.js';

let fen = FEN.start;
fen = 'rnbqk2r/p1p1bppp/4pn2/1pPP4/Q7/8/PP1P1PPP/RNB1KBNR w KQkq b6 0 6';

const chess = new Chess();
chess.load(fen);

const board = new Chessboard(document.getElementById('board'), {
    position: fen,
    assetsUrl: '/cm-chessboard/assets/', // wherever you copied the assets folder to, could also be in the node_modules folder
    extensions: [{ class: Markers }],
    style: {
        pieces: {
            file: '/cm-chessboard/assets/pieces/staunty.svg',
        }
    }
});

board.enableMoveInput(inputHandler, chess.turn() === 'w' ? COLOR.white : COLOR.black);

function inputHandler(event) {
    console.log(event);

    switch (event.type) {
        case INPUT_EVENT_TYPE.moveInputStarted:
            return true;
        case INPUT_EVENT_TYPE.validateMoveInput:

            try {
                const moveObject = { from: event.squareFrom, to: event.squareTo };
                if (event.piece.substring(1) === 'p' && (event.squareTo.substring(1) === '1' || event.squareTo.substring(1) === '8')) {
                    // TODO Get promotion choice
                    moveObject.promotion = 'q';
                }
                const move = chess.move(moveObject);
                console.log(move);

                // Castling and en passant
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
                }

                // Switch turn
                board.disableMoveInput();
                board.enableMoveInput(inputHandler, chess.turn() === 'w' ? COLOR.white : COLOR.black);
            } catch (e) {
                console.log(e);
                return false;
            }
            return true;
        case INPUT_EVENT_TYPE.moveInputFinished:
            // Board update in case of promotion
            if (chess.history().length > 0) {
                const lastMove = chess.history({ verbose: true }).at(-1);
                if (lastMove.flags.includes('p')) {
                    board.setPiece(lastMove.to, lastMove.color + lastMove.promotion);
                }
            }
            break;
    }
}
