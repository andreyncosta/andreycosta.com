<?php
declare(strict_types=1);

/** @return array<string, array{pt: string, en: string}> */
function page_copy(): array
{
    return [
        'unchecked' => [
            'pt' => 'Marque o aceite para ver a nota.',
            'en' => 'Check the consent box to see the score.',
        ],
        'bad_email' => [
            'pt' => 'O e-mail não é válido.',
            'en' => 'The email address is not valid.',
        ],
        'empty_posting' => [
            'pt' => 'O anúncio está vazio.',
            'en' => 'The job posting is empty.',
        ],
        'missing_file' => [
            'pt' => 'Escolha o arquivo do currículo.',
            'en' => 'Choose the résumé file.',
        ],
        'too_large' => [
            'pt' => 'O arquivo é maior do que 5 MB.',
            'en' => 'The file is larger than 5 MB.',
        ],
        'bad_type' => [
            'pt' => 'Envie um arquivo PDF ou DOCX.',
            'en' => 'Send a PDF or DOCX file.',
        ],
        'rate_limited' => [
            'pt' => 'Nesta hora já houve envios demais. Tente de novo mais tarde.',
            'en' => 'Too many submissions were sent this hour. Try again later.',
        ],
        'unreadable' => [
            'pt' => 'Não foi possível ler o arquivo.',
            'en' => 'The file could not be read.',
        ],
        'pdf_unavailable' => [
            'pt' => 'Não foi possível ler o PDF neste servidor. Envie o currículo em .docx.',
            'en' => 'This server could not read the PDF. Send the resume as a .docx file.',
        ],
    ];
}

/**
 * @return array{
 *   title: array{pt: string, en: string},
 *   email: array{pt: string, en: string},
 *   file: array{pt: string, en: string},
 *   posting: array{pt: string, en: string},
 *   hint: array{pt: string, en: string},
 *   first_line: array{pt: string, en: string},
 *   button: array{pt: string, en: string},
 *   consent_en: string,
 *   score: array{pt: string, en: string},
 *   of: array{pt: string, en: string},
 *   integer: array{pt: string, en: string},
 *   tenth: array{pt: string, en: string},
 *   wait: array{pt: string, en: string},
 *   legend: array{pt: string, en: string},
 *   mail_failed: array{pt: string, en: string},
 *   fallback: array{pt: string, en: string},
 *   checks: array<string, array{pt: string, en: string}>,
 *   about: array{pt: list<string>, en: list<string>}
 * }
 */
function page_text(): array
{
    return [
        'title' => ['pt' => 'Avaliar currículo', 'en' => 'Score a résumé'],
        'email' => ['pt' => 'E-mail', 'en' => 'Email'],
        'file' => ['pt' => 'Arquivo do currículo', 'en' => 'Résumé file'],
        'posting' => ['pt' => 'Descrição da vaga', 'en' => 'Job posting'],
        'hint' => [
            'pt' => 'Copie o anúncio e cole aqui.',
            'en' => 'Copy the posting and paste it here.',
        ],
        'first_line' => [
            'pt' => 'A primeira linha é o cargo.',
            'en' => 'The first line is the job title.',
        ],
        'button' => ['pt' => 'Avaliar', 'en' => 'Evaluate'],
        'consent_en' => 'Andrey Costa keeps the email to reply to this evaluation and deletes the message when the person replies to andreynacosta46@gmail.com asking for deletion.',
        'score' => ['pt' => 'Nota', 'en' => 'Score'],
        'of' => ['pt' => 'de 10', 'en' => 'out of 10'],
        'integer' => ['pt' => 'Inteiro', 'en' => 'Integer'],
        'tenth' => ['pt' => 'Décimo', 'en' => 'Tenth'],
        'wait' => [
            'pt' => 'A nota aparece depois do envio.',
            'en' => 'The score appears after you submit.',
        ],
        'legend' => [
            'pt' => 'A marca vermelha é uma checagem que não passou.',
            'en' => 'A red mark is a check that did not pass.',
        ],
        'mail_failed' => [
            'pt' => 'O contato não foi enviado.',
            'en' => 'The contact was not sent.',
        ],
        'fallback' => [
            'pt' => 'Não foi possível avaliar o currículo.',
            'en' => 'The résumé could not be scored.',
        ],
        'checks' => [
            'one_page' => [
                'pt' => 'O currículo cabe em uma página.',
                'en' => 'The résumé fits on one page.',
            ],
            'linear' => [
                'pt' => 'O texto segue em uma coluna, sem tabela e sem desenho.',
                'en' => 'The text runs in one column, with no table and no drawing.',
            ],
            'objective' => [
                'pt' => 'O cargo da primeira linha do anúncio aparece no Objetivo, no começo do currículo.',
                'en' => 'The job from the first line of the posting appears in the Objective, near the start of the résumé.',
            ],
            'keywords' => [
                'pt' => 'Os termos do anúncio aparecem no corpo, fora do Objetivo.',
                'en' => 'Terms from the posting appear in the body, outside the Objective.',
            ],
            'bullet' => [
                'pt' => 'Um item começa com verbo de ação e traz um resultado.',
                'en' => 'One bullet starts with an action verb and includes a result.',
            ],
            'authority' => [
                'pt' => 'O começo do currículo traz um termo de autoridade.',
                'en' => 'The start of the résumé includes a term of authority.',
            ],
            'docx' => [
                'pt' => 'O arquivo é um .docx válido.',
                'en' => 'The file is a valid .docx.',
            ],
        ],
        'about' => [
            'pt' => [
                'A nota usa regras fixas e não simula um sistema de triagem.',
                'Andrey Costa é o controlador do e-mail.',
                'O arquivo do currículo é lido e apagado em seguida.',
                'Para pedir o apagamento do e-mail, responda para andreynacosta46@gmail.com.',
            ],
            'en' => [
                'The score uses fixed rules and does not simulate an applicant tracking system.',
                'Andrey Costa is the controller of the email.',
                'The résumé file is read and then deleted.',
                'To ask for the email to be deleted, reply to andreynacosta46@gmail.com.',
            ],
        ],
    ];
}
