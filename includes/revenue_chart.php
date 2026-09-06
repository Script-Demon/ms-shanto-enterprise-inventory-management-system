<?php
/**
 * Revenue column chart, rendered server-side as inline SVG.
 * No charting library — keeps the app dependency-free.
 */

// Rounds an axis maximum up to a clean tick step (1/2/2.5/5/10 × 10^n).
function nice_axis_max($max, $ticks = 4) {
    if ($max <= 0) {
        return [1000.0, 250.0];
    }
    $rawStep = $max / $ticks;
    $mag = pow(10, floor(log10($rawStep)));
    $norm = $rawStep / $mag;
    if ($norm <= 1)        { $step = 1; }
    elseif ($norm <= 2)    { $step = 2; }
    elseif ($norm <= 2.5)  { $step = 2.5; }
    elseif ($norm <= 5)    { $step = 5; }
    else                   { $step = 10; }
    $step *= $mag;
    return [ceil($max / $step) * $step, $step];
}

/**
 * @param array $rows  [['date' => 'Y-m-d', 'revenue' => float], ...] oldest first
 */
function render_revenue_chart(array $rows) {
    global $config;
    $currency = $config['currency'] ?? '';

    $W = 880; $H = 262;
    $padL = 60; $padR = 14; $padT = 26; $padB = 34;
    $plotW = $W - $padL - $padR;
    $plotH = $H - $padT - $padB;
    $baseline = $padT + $plotH;

    $n = max(count($rows), 1);
    $band = $plotW / $n;
    $barW = min(24, $band * 0.55);

    $maxVal = 0.0;
    foreach ($rows as $r) {
        $maxVal = max($maxVal, (float)$r['revenue']);
    }
    list($axisMax, $step) = nice_axis_max($maxVal);

    // With many bars the date labels would collide, so show every Nth —
    // counted back from the most recent day, which is always labelled.
    $labelEvery = max(1, (int)ceil($n / 15));

    // Only the peak day gets a direct label — never a number on every bar.
    $peakIndex = -1;
    if ($maxVal > 0) {
        foreach ($rows as $i => $r) {
            if ((float)$r['revenue'] >= $maxVal) { $peakIndex = $i; break; }
        }
    }

    $out  = '<div class="chart">';
    $out .= '<svg viewBox="0 0 ' . $W . ' ' . $H . '" role="img" preserveAspectRatio="xMidYMid meet" '
          . 'aria-label="' . e(t('dash_revenue_chart')) . '">';

    // Gridlines + y ticks
    for ($v = 0; $v <= $axisMax + 0.0001; $v += $step) {
        $y = $baseline - ($axisMax > 0 ? ($v / $axisMax) * $plotH : 0);
        $y = round($y, 2);
        $out .= '<line class="grid" x1="' . $padL . '" y1="' . $y . '" x2="' . ($W - $padR) . '" y2="' . $y . '"/>';
        $out .= '<text class="axis-text" x="' . ($padL - 9) . '" y="' . ($y + 3.5) . '" text-anchor="end">'
              . e(number_format($v)) . '</text>';
    }

    foreach ($rows as $i => $r) {
        $v  = (float)$r['revenue'];
        $h  = ($axisMax > 0) ? ($v / $axisMax) * $plotH : 0;
        $h  = round($h, 2);
        $cx = round($padL + $band * $i + $band / 2, 2);
        $x  = round($cx - $barW / 2, 2);
        $y  = round($baseline - $h, 2);
        $bx = round($padL + $band * $i, 2);
        $label = date('j/n', strtotime($r['date']));
        $tipDate = date('j M Y', strtotime($r['date']));
        $tipVal = $currency . number_format($v, 2);

        $out .= '<g class="cbar">';
        $out .= '<title>' . e($tipDate . ' — ' . $tipVal) . '</title>';
        // Full-band hit target, comfortably larger than the mark
        $out .= '<rect class="hit" x="' . $bx . '" y="' . $padT . '" width="' . round($band, 2) . '" height="' . $plotH . '" rx="6"/>';

        if ($h > 0) {
            $r4 = min(4, $h);
            // rounded data-end on top, square at the baseline
            $out .= '<path class="bar-mark" d="'
                  . 'M' . $x . ',' . $baseline
                  . ' L' . $x . ',' . round($y + $r4, 2)
                  . ' Q' . $x . ',' . $y . ' ' . round($x + $r4, 2) . ',' . $y
                  . ' L' . round($x + $barW - $r4, 2) . ',' . $y
                  . ' Q' . round($x + $barW, 2) . ',' . $y . ' ' . round($x + $barW, 2) . ',' . round($y + $r4, 2)
                  . ' L' . round($x + $barW, 2) . ',' . $baseline . ' Z"/>';
        }

        // Direct label on the peak day only
        if ($i === $peakIndex) {
            $out .= '<text class="peak-label" x="' . $cx . '" y="' . round($y - 8, 2) . '" text-anchor="middle">'
                  . e($currency . number_format($v)) . '</text>';
        }

        // x-axis tick (thinned when there are many days)
        if (($n - 1 - $i) % $labelEvery === 0) {
            $out .= '<text class="axis-text" x="' . $cx . '" y="' . ($baseline + 18) . '" text-anchor="middle">'
                  . e($label) . '</text>';
        }

        // Hover tooltip (enhances — the peak is labelled and the table view has every value)
        $tw = 118; $th = 42;
        $tx = min(max($cx - $tw / 2, 2), $W - $tw - 2);
        $ty = max($y - $th - 10, 2);
        $out .= '<g class="tip" transform="translate(' . round($tx, 2) . ',' . round($ty, 2) . ')">';
        $out .= '<rect class="tip-box" width="' . $tw . '" height="' . $th . '" rx="8"/>';
        $out .= '<text class="tip-title" x="10" y="16">' . e($tipDate) . '</text>';
        $out .= '<text class="tip-value" x="10" y="32">' . e($tipVal) . '</text>';
        $out .= '</g>';

        $out .= '</g>';
    }

    // Baseline rule
    $out .= '<line class="axis-rule" x1="' . $padL . '" y1="' . $baseline . '" x2="' . ($W - $padR) . '" y2="' . $baseline . '"/>';
    $out .= '</svg></div>';

    return $out;
}
