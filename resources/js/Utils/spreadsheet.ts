/**
 * Converte um índice baseado em 0 para uma letra de coluna do Excel/Sheets.
 * Ex: 0 -> A, 25 -> Z, 26 -> AA
 */
export function indexToColumnLetter(index: number): string {
    let letter = '';
    while (index >= 0) {
        letter = String.fromCharCode((index % 26) + 65) + letter;
        index = Math.floor(index / 26) - 1;
    }
    return letter;
}

/**
 * Retorna a notação A1 para uma célula baseada na linha e coluna (0-indexed).
 * Ex: row=0, col=0 -> A1
 */
export function getCellReference(row: number, col: number): string {
    return `${indexToColumnLetter(col)}${row + 1}`;
}
