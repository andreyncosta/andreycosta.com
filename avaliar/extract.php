<?php
declare(strict_types=1);

/**
 * @return array{text:string, pages:int, linear:bool, readable:bool, valid_docx:bool}
 */
function extract_file(string $path, string $extension, ?callable $runner = null): array
{
    // Docx ignores $runner. PDF stays unreadable until its branch calls the runner.
    if ($extension === 'docx') {
        return extract_docx($path);
    }
    return extract_unreadable();
}

function docx_is_linear(string $documentXml): bool
{
    if (str_contains($documentXml, 'w:tbl') || str_contains($documentXml, 'w:drawing')) {
        return false;
    }
    $found = preg_match_all('/<w:cols\b[^>]*>/', $documentXml, $tags);
    if (!is_int($found) || $found < 1) {
        return true;
    }
    foreach ($tags[0] as $tag) {
        if (preg_match('/\bw:num=(["\'])(\d+)\1/', $tag, $num) === 1 && (int) $num[2] > 1) {
            return false;
        }
    }
    return true;
}

/** @return array{text:string, pages:int, linear:bool, readable:bool, valid_docx:bool} */
function extract_unreadable(): array
{
    return [
        'text' => '',
        'pages' => 0,
        'linear' => false,
        'readable' => false,
        'valid_docx' => false,
    ];
}

/** @return array{text:string, pages:int, linear:bool, readable:bool, valid_docx:bool} */
function extract_docx(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return extract_unreadable();
    }
    $zip = new ZipArchive();
    $opened = $zip->open($path);
    if ($opened !== true) {
        return extract_unreadable();
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!is_string($xml)) {
        return extract_unreadable();
    }
    return [
        'text' => docx_plain_text($xml),
        'pages' => 0,
        'linear' => docx_is_linear($xml),
        'readable' => true,
        'valid_docx' => true,
    ];
}

function docx_plain_text(string $documentXml): string
{
    if (trim($documentXml) === '') {
        return '';
    }
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML($documentXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if ($loaded !== true) {
        return docx_strip_tags($documentXml);
    }

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $lines = [];
    foreach ($dom->getElementsByTagNameNS($ns, 'p') as $paragraph) {
        $lines[] = docx_node_text($paragraph, $ns, true);
    }
    return implode("\n", $lines);
}

function docx_node_text(DOMNode $node, string $ns, bool $root = false): string
{
    if ($node instanceof DOMElement && $node->namespaceURI === $ns) {
        if ($node->localName === 't') {
            return $node->textContent;
        }
        if ($node->localName === 'tab') {
            return ' ';
        }
        if ($node->localName === 'br' || $node->localName === 'cr') {
            return "\n";
        }
        if ($node->localName === 'p' && !$root) {
            return '';
        }
    }
    $text = '';
    foreach ($node->childNodes as $child) {
        $text .= docx_node_text($child, $ns);
    }
    return $text;
}

function docx_strip_tags(string $documentXml): string
{
    $marked = preg_replace(
        ['/<\/w:p>/', '/<w:(?:br|cr)\b[^>]*\/>/', '/<w:tab\b[^>]*\/>/'],
        ["\n", "\n", ' '],
        $documentXml
    );
    if (!is_string($marked)) {
        $marked = $documentXml;
    }
    return html_entity_decode(strip_tags($marked), ENT_QUOTES | ENT_XML1, 'UTF-8');
}
