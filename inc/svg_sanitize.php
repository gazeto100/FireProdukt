<?php
declare(strict_types=1);

/**
 * Почиства SVG снимката на прегледа, изпратена от браузъра.
 * Оставя само позволени елементи и атрибути; премахва скриптове, събития и външни връзки.
 * (Админът я показва през <img>, където скриптове така или иначе не се изпълняват.)
 */
function svg_sanitize(string $svg, int $maxBytes = 400000): ?string
{
    if ($svg === '' || strlen($svg) > $maxBytes || stripos($svg, '<!ENTITY') !== false || stripos($svg, '<!DOCTYPE') !== false) {
        return null;
    }
    $allowedEl = array_flip([
        'svg', 'g', 'defs', 'mask', 'clipPath', 'rect', 'path', 'polygon', 'polyline', 'circle', 'ellipse', 'line',
        'text', 'tspan', 'linearGradient', 'radialGradient', 'stop', 'pattern', 'style', 'title', 'desc',
        'filter', 'feGaussianBlur', 'feMerge', 'feMergeNode', 'feOffset', 'feFlood', 'feComposite', 'feColorMatrix',
    ]);
    $allowedAttr = array_flip([
        'id', 'class', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'width', 'height',
        'd', 'points', 'transform', 'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity',
        'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'opacity', 'mask', 'clip-path', 'clip-rule', 'filter',
        'viewBox', 'preserveAspectRatio', 'xmlns', 'version', 'offset', 'stop-color', 'stop-opacity',
        'gradientUnits', 'gradientTransform', 'patternUnits', 'patternTransform', 'maskUnits', 'maskContentUnits',
        'clipPathUnits', 'font-family', 'font-size', 'font-weight', 'text-anchor', 'dominant-baseline', 'letter-spacing',
        'textLength', 'lengthAdjust', 'stdDeviation', 'in', 'in2', 'result', 'dx', 'dy', 'operator', 'values', 'type',
        'flood-color', 'flood-opacity', 'role', 'aria-label', 'style', 'vector-effect', 'shape-rendering', 'paint-order',
    ]);

    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok || !$doc->documentElement || $doc->documentElement->localName !== 'svg') {
        return null;
    }

    $walk = function (DOMNode $node) use (&$walk, $allowedEl, $allowedAttr): void {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMElement) {
                if (!isset($allowedEl[$child->localName])) {
                    $node->removeChild($child);
                    continue;
                }
                for ($j = $child->attributes->length - 1; $j >= 0; $j--) {
                    $attr = $child->attributes->item($j);
                    $name = $attr->nodeName;
                    $val = $attr->nodeValue;
                    if (!isset($allowedAttr[$name]) || svg_value_unsafe($val)) {
                        $child->removeAttribute($name);
                    }
                }
                if ($child->localName === 'style') {
                    $css = $child->textContent;
                    if (preg_match('/@import|expression\s*\(|javascript:/i', $css)
                        || preg_match_all('/url\(\s*[\'"]?([^\'")]*)/i', $css, $m) && array_filter($m[1], fn($u) => !preg_match('#^(data:font/woff2;base64,|\#)#', trim($u)))) {
                        $node->removeChild($child);
                        continue;
                    }
                }
                $walk($child);
            } elseif ($child instanceof DOMProcessingInstruction || $child instanceof DOMComment) {
                $node->removeChild($child);
            }
        }
    };
    $root = $doc->documentElement;
    for ($j = $root->attributes->length - 1; $j >= 0; $j--) {
        $attr = $root->attributes->item($j);
        if (!isset($allowedAttr[$attr->nodeName]) || svg_value_unsafe($attr->nodeValue)) {
            $root->removeAttribute($attr->nodeName);
        }
    }
    $walk($root);
    $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    return $doc->saveXML($root);
}

function svg_value_unsafe(string $v): bool
{
    if (preg_match('/javascript:|data:(?!font\/woff2)|expression\s*\(/i', $v)) {
        return true;
    }
    // url() само към вътрешни id-та: url(#grad)
    if (preg_match_all('/url\(\s*[\'"]?([^\'")]*)/i', $v, $m)) {
        foreach ($m[1] as $u) {
            if (!str_starts_with(trim($u), '#')) {
                return true;
            }
        }
    }
    return false;
}
