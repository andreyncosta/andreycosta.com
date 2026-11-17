<?php
declare(strict_types=1);

/**
 * @return array{text:string, pages:int, linear:bool, readable:bool, valid_docx:bool}
 */
function extract_file(string $path, string $extension, ?callable $runner = null): array
{
    // Docx ignores $runner. A null runner reads PDF with proc_open.
    if ($extension === 'docx') {
        return extract_docx($path);
    }
    if ($extension === 'pdf') {
        return extract_pdf($path, $runner ?? extract_command(...));
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

/** @return array{text:string, pages:int, linear:bool, readable:bool, valid_docx:bool} */
function extract_pdf(string $path, callable $runner): array
{
    $info = $runner(['pdfinfo', $path]);
    $layout = $runner(['pdftotext', '-layout', $path, '-']);
    $infoCode = is_array($info) && is_int($info['code'] ?? null) ? $info['code'] : 1;
    $layoutCode = is_array($layout) && is_int($layout['code'] ?? null) ? $layout['code'] : 1;
    $infoOut = is_array($info) && is_string($info['stdout'] ?? null) ? $info['stdout'] : '';
    $layoutOut = is_array($layout) && is_string($layout['stdout'] ?? null) ? $layout['stdout'] : '';
    $text = $layoutCode === 0 ? $layoutOut : '';
    // A blank page is a form feed, which trim() does not strip.
    $readable = $infoCode === 0 && $layoutCode === 0 && trim($text, " \n\r\t\v\0\f") !== '';
    return [
        'text' => $text,
        'pages' => $infoCode === 0 ? pdfinfo_pages($infoOut) : 0,
        'linear' => $readable && !layout_has_column($text),
        'readable' => $readable,
        'valid_docx' => false,
    ];
}

function pdfinfo_pages(string $info): int
{
    if (preg_match('/^Pages:[ \t]*([0-9]+)/m', $info, $match) !== 1) {
        return 0;
    }
    return (int) $match[1];
}

function layout_has_column(string $layout): bool
{
    $lines = preg_split("/\r\n|\n|\r|\f/", $layout);
    if (!is_array($lines)) {
        return false;
    }
    $starts = [];
    foreach ($lines as $line) {
        $start = layout_gap_start($line);
        if ($start !== null) {
            $starts[] = $start;
        }
    }
    $count = count($starts);
    if ($count < 4) {
        return false;
    }
    sort($starts, SORT_NUMERIC);
    for ($i = 0; $i <= $count - 4; $i++) {
        if ($starts[$i + 3] - $starts[$i] <= 2) {
            return true;
        }
    }
    return false;
}

function layout_gap_start(string $line): ?int
{
    $length = strlen($line);
    $i = 0;
    $column = 0;
    while ($i < $length) {
        if ($line[$i] !== ' ') {
            $column++;
            $i += layout_utf8_width($line, $i);
            continue;
        }
        $start = $column;
        $spaces = 0;
        while ($i < $length && $line[$i] === ' ') {
            $spaces++;
            $column++;
            $i++;
        }
        if ($spaces >= 8 && $start > 0 && $i < $length) {
            return $start;
        }
    }
    return null;
}

function layout_utf8_width(string $text, int $i): int
{
    $byte = ord($text[$i]);
    $width = 1;
    if ($byte >= 0xF0) {
        $width = 4;
    } elseif ($byte >= 0xE0) {
        $width = 3;
    } elseif ($byte >= 0xC0) {
        $width = 2;
    }
    $remain = strlen($text) - $i;
    return $width < $remain ? $width : $remain;
}

/** @return array{code:int, stdout:string, stderr:string} */
function extract_command(array $argv): array
{
    $failed = ['code' => 127, 'stdout' => '', 'stderr' => ''];
    $errPath = tempnam(sys_get_temp_dir(), 'avaliar-err');
    if (!is_string($errPath)) {
        return $failed;
    }
    $env = getenv();
    if (!is_array($env)) {
        $env = [];
    }
    // pdfinfo translates Pages: unless messages are C. UTF-8 keeps the text.
    $env['LC_ALL'] = 'C.UTF-8';
    $env['LANG'] = 'C.UTF-8';
    $env['LANGUAGE'] = 'C';
    $proc = proc_open($argv, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $errPath, 'w'],
    ], $pipes, null, $env);
    if (!is_resource($proc)) {
        if (is_file($errPath)) {
            unlink($errPath);
        }
        return $failed;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($proc);
    $stderr = file_get_contents($errPath);
    if (is_file($errPath)) {
        unlink($errPath);
    }
    return [
        'code' => $code,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}
