
import { FEN } from 'cm-chessboard'

import { VariationChessboardEngine } from './chessboardengine/variation-cbe.js';

let fen = FEN.start;
// fen = 'rnbqk2r/p1p1bppp/4pn2/1pPP4/Q7/8/PP1P1PPP/RNB1KBNR w KQkq b6 0 6';

let PGN = '[Event "Panzani: Chapitre 1 : Fc5"]\n[Site "https://lichess.org/study/EWjlne2Z/NvbkMMM5"]\n[Result "*"]\n[Variant "Standard"]\n[ECO "?"]\n[Opening "?"]\n[Annotator "https://lichess.org/@/Jilyfe"]\n[FEN "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1"]\n[SetUp "1"]\n[UTCDate "2023.07.26"]\n[UTCTime "08:49:21"]\n[ChapterMode "gamebook"]\n\n1. e4 e5 (1... c6 2. d4 d5 3. e5) 2. Nc3 Nf6 (2... Nc6 3. Bc4) 3. f4';


let board;

document.addEventListener('DOMContentLoaded', function () {

    board = new VariationChessboardEngine(document.getElementById('board'), 'w', PGN);

    board.enablePlayableMove();

    /*
    document.addEventListener('keydown', (event) => {
        switch (event.key) {
            case 'ArrowLeft':
                board.previousMove();
                break;
            case 'ArrowRight':
                board.nextMove();
                break;
        }
    });
    */

});