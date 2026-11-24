// Painel de contagem do QR v4 (só administrador logado). SEM JavaScript. Gráficos em SVG/CSS com animação.
// Acesse: queroqueropag.com.br/home/?ver_contagem_qr=1
add_action('template_redirect', function () {
  if (!isset($_GET['ver_contagem_qr']) || !current_user_can('manage_options')) return;

  $dados = get_option('contagem_abrir_app', []);
  if (!is_array($dados)) $dados = [];
  ksort($dados);

  $tema   = (isset($_GET['tema']) && $_GET['tema'] === 'claro') ? 'claro' : 'escuro';
  $escuro = ($tema === 'escuro');

  // ---- período ----
  $hoje    = wp_date('Y-m-d');
  $periodo = isset($_GET['periodo']) ? sanitize_key($_GET['periodo']) : '30';
  $re_data = '/^\d{4}-\d{2}-\d{2}$/';

  if (isset($_GET['de']) && preg_match($re_data, $_GET['de'])) {
    $de      = $_GET['de'];
    $ate     = (isset($_GET['ate']) && preg_match($re_data, $_GET['ate'])) ? $_GET['ate'] : $hoje;
    $periodo = 'custom';
  } elseif ($periodo === 'tudo') {
    $chaves = array_keys($dados);
    $de  = $chaves ? $chaves[0] : $hoje;
    $ate = $hoje;
  } else {
    $n       = ($periodo === '7') ? 7 : (($periodo === '90') ? 90 : 30);
    $periodo = (string) $n;
    $de      = wp_date('Y-m-d', time() - ($n - 1) * DAY_IN_SECONDS);
    $ate     = $hoje;
  }

  $nomes = [
    'site' => 'Site (QR)', 'site-selo' => 'Site (selos das lojas)', 'loja' => 'Loja física',
    'impresso' => 'Material impresso', 'campanha' => 'Campanha', 'outras' => 'Outras origens',
    'sem_origem' => 'Sem origem (QRs antigos)',
  ];
  if ($escuro) {
    $cores = ['site' => '#4CB574', 'site-selo' => '#8FD3A5', 'loja' => '#F1CB0D', 'impresso' => '#E08A1E',
              'campanha' => '#5FA3E0', 'outras' => '#9AA3AD', 'sem_origem' => '#566872'];
    $c_site = '#4CB574'; $c_outros = '#566872'; $c_total = '#F1CB0D';
    $c_os = ['ios' => '#E6EFE9', 'android' => '#4CB574', 'outro' => '#F1CB0D'];
  } else {
    $cores = ['site' => '#24723B', 'site-selo' => '#6BAF7E', 'loja' => '#F1CB0D', 'impresso' => '#E08A1E',
              'campanha' => '#3B82C4', 'outras' => '#8A8F98', 'sem_origem' => '#C9CED6'];
    $c_site = '#24723B'; $c_outros = '#C9CED6'; $c_total = '#d4a900';
    $c_os = ['ios' => '#142B1C', 'android' => '#24723B', 'outro' => '#F1CB0D'];
  }

  // ---- agrega os dados do período ----
  $dias       = [];
  $tot_origem = [];
  $tot_os     = ['ios' => 0, 'android' => 0, 'outro' => 0];

  $t_ini = strtotime($de . ' 12:00:00');
  $t_fim = strtotime($ate . ' 12:00:00');
  for ($t = $t_ini, $i = 0; $t && $t <= $t_fim && $i < 400; $t += DAY_IN_SECONDS, $i++) {
    $dia = wp_date('Y-m-d', $t);
    $d   = isset($dados[$dia]) && is_array($dados[$dia]) ? $dados[$dia] : [];

    $ios     = (int) (isset($d['ios']) ? $d['ios'] : 0);
    $android = (int) (isset($d['android']) ? $d['android'] : 0);
    $outro   = (int) (isset($d['outro']) ? $d['outro'] : 0);
    $total   = $ios + $android + $outro;

    $orig = isset($d['origem']) && is_array($d['origem']) ? $d['origem'] : [];
    $soma = array_sum(array_map('intval', $orig));
    if ($total > $soma) {
      $orig['sem_origem'] = (int) (isset($orig['sem_origem']) ? $orig['sem_origem'] : 0) + ($total - $soma);
    }
    $site = (int) (isset($orig['site']) ? $orig['site'] : 0) + (int) (isset($orig['site-selo']) ? $orig['site-selo'] : 0);

    $dias[$dia] = ['total' => $total, 'site' => $site, 'outros' => $total - $site,
                   'ios' => $ios, 'android' => $android, 'outro' => $outro];

    foreach ($orig as $o => $n) {
      $tot_origem[$o] = (isset($tot_origem[$o]) ? $tot_origem[$o] : 0) + (int) $n;
    }
    $tot_os['ios'] += $ios;
    $tot_os['android'] += $android;
    $tot_os['outro'] += $outro;
  }

  $nd     = count($dias);
  $geral  = array_sum(array_column($dias, 'total'));
  $site_t = array_sum(array_column($dias, 'site'));
  $outr_t = $geral - $site_t;
  $pct    = $geral ? round($site_t / $geral * 100, 1) : 0;
  $media  = $nd ? round($geral / $nd, 1) : 0;

  $melhor = ['dia' => '-', 'n' => 0];
  foreach ($dias as $dk => $x) {
    if ($x['total'] > $melhor['n']) $melhor = ['dia' => substr($dk, 8, 2) . '/' . substr($dk, 5, 2), 'n' => $x['total']];
  }

  // ---- período anterior (mesmo número de dias, logo antes) ----
  $tem_prev   = ($periodo !== 'tudo' && $nd > 0);
  $prev_total = 0;
  $prev_site  = 0;
  if ($tem_prev) {
    for ($j = 1; $j <= $nd; $j++) {
      $dk = wp_date('Y-m-d', $t_ini - $j * DAY_IN_SECONDS);
      $d  = isset($dados[$dk]) && is_array($dados[$dk]) ? $dados[$dk] : [];
      $pt = (int) (isset($d['ios']) ? $d['ios'] : 0) + (int) (isset($d['android']) ? $d['android'] : 0) + (int) (isset($d['outro']) ? $d['outro'] : 0);
      $po = isset($d['origem']) && is_array($d['origem']) ? $d['origem'] : [];
      $prev_total += $pt;
      $prev_site  += (int) (isset($po['site']) ? $po['site'] : 0) + (int) (isset($po['site-selo']) ? $po['site-selo'] : 0);
    }
  }
  $prev_outros = $prev_total - $prev_site;
  $rot_prev = $tem_prev ? wp_date('d/m', $t_ini - $nd * DAY_IN_SECONDS) . ' &ndash; ' . wp_date('d/m', $t_ini - DAY_IN_SECONDS) : '';

  $delta = function ($cur, $prev) use ($tem_prev) {
    if (!$tem_prev) return '<span class="dl nd">-</span>';
    if ($prev == 0) return '<span class="dl nd">' . ($cur ? 'novo' : '-') . '</span>';
    $p = ($cur - $prev) / $prev * 100;
    return '<span class="dl ' . ($p >= 0 ? 'up' : 'dn') . '">' . ($p >= 0 ? '&#8593;' : '&#8595;') . ' '
      . number_format(abs($p), 1, ',', '.') . '%</span>';
  };
  $linha = function ($ic, $lb, $sub, $val, $dl) {
    return '<div class="row"><div class="ic">' . $ic . '</div><div class="lb"><b>' . $lb . '</b><small>' . $sub . '</small></div>'
      . '<div class="vl"><strong><span class="num" style="--n:' . (int) $val . '"></span></strong>' . $dl . '</div></div>';
  };

  // ---- gráfico de linhas (SVG) ----
  $W = 760; $H = 290; $ml = 34; $mr = 12; $mt = 14; $mb = 30;
  $pw = $W - $ml - $mr;
  $ph = $H - $mt - $mb;
  $mx = 1;
  foreach ($dias as $x) if ($x['total'] > $mx) $mx = $x['total'];
  $stepy = max(1, (int) ceil($mx / 4));
  $ymax  = $stepy * 4;
  $X = function ($i) use ($ml, $pw, $nd) { return $nd > 1 ? $ml + $pw * $i / ($nd - 1) : $ml + $pw / 2; };
  $Y = function ($v) use ($mt, $ph, $ymax) { return $mt + $ph - ($v / $ymax * $ph); };
  $suave = function ($vals) use ($X, $Y) {
    $pts = [];
    foreach (array_values($vals) as $i => $v) $pts[] = [$X($i), $Y($v)];
    $d = 'M' . round($pts[0][0], 1) . ',' . round($pts[0][1], 1);
    for ($i = 1; $i < count($pts); $i++) {
      $cx = ($pts[$i - 1][0] + $pts[$i][0]) / 2;
      $d .= ' C' . round($cx, 1) . ',' . round($pts[$i - 1][1], 1) . ' ' . round($cx, 1) . ',' . round($pts[$i][1], 1)
        . ' ' . round($pts[$i][0], 1) . ',' . round($pts[$i][1], 1);
    }
    return [$d, $pts];
  };

  $svg = '';
  if ($nd > 0) {
    $tot_v  = array_values(array_column($dias, 'total'));
    $site_v = array_values(array_column($dias, 'site'));
    $rots   = [];
    foreach (array_keys($dias) as $dk) $rots[] = substr($dk, 8, 2) . '/' . substr($dk, 5, 2);
    list($dt, $pt) = $suave($tot_v);
    list($ds, $ps) = $suave($site_v);
    $base   = $Y(0);
    $area_t = $dt . ' L' . round($pt[$nd - 1][0], 1) . ',' . round($base, 1) . ' L' . round($pt[0][0], 1) . ',' . round($base, 1) . ' Z';
    $area_s = $ds . ' L' . round($ps[$nd - 1][0], 1) . ',' . round($base, 1) . ' L' . round($ps[0][0], 1) . ',' . round($base, 1) . ' Z';

    $svg  = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Escaneamentos por dia">';
    $svg .= '<defs><linearGradient id="gt" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $c_total . '" stop-opacity=".35"/><stop offset="1" stop-color="' . $c_total . '" stop-opacity="0"/></linearGradient>'
      . '<linearGradient id="gs" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $c_site . '" stop-opacity=".4"/><stop offset="1" stop-color="' . $c_site . '" stop-opacity="0"/></linearGradient></defs>';
    for ($k = 0; $k <= 4; $k++) {
      $y = $Y($stepy * $k);
      $svg .= '<line class="gl" x1="' . $ml . '" x2="' . ($W - $mr) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>'
        . '<text class="ax" x="' . ($ml - 8) . '" y="' . round($y + 4, 1) . '" text-anchor="end">' . ($stepy * $k) . '</text>';
    }
    $lab = max(1, (int) ceil($nd / 8));
    foreach ($rots as $i => $r) {
      if ($i % $lab === 0) $svg .= '<text class="ax" x="' . round($X($i), 1) . '" y="' . ($H - 8) . '" text-anchor="middle">' . esc_html($r) . '</text>';
    }
    $svg .= '<path class="ar" d="' . $area_t . '" fill="url(#gt)"/><path class="ar" d="' . $area_s . '" fill="url(#gs)"/>'
      . '<path class="ln" pathLength="1" d="' . $dt . '" stroke="' . $c_total . '"/>'
      . '<path class="ln" pathLength="1" d="' . $ds . '" stroke="' . $c_site . '" style="animation-delay:.6s"/>';

    $cw = $nd > 1 ? $pw / ($nd - 1) : $pw;
    foreach ($rots as $i => $r) {
      $x  = $X($i);
      $tw = 112; $th = 58;
      $tx = min(max($x - $tw / 2, 2), $W - $tw - 2);
      $svg .= '<g class="hv"><rect class="hit" x="' . round($x - $cw / 2, 1) . '" y="' . $mt . '" width="' . round($cw, 1) . '" height="' . $ph . '"/>'
        . '<line class="dt gl" x1="' . round($x, 1) . '" x2="' . round($x, 1) . '" y1="' . $mt . '" y2="' . round($base, 1) . '" style="stroke-dasharray:none"/>'
        . '<circle class="dt" cx="' . round($x, 1) . '" cy="' . round($Y($tot_v[$i]), 1) . '" r="4.5" fill="' . $c_total . '"/>'
        . '<circle class="dt" cx="' . round($x, 1) . '" cy="' . round($Y($site_v[$i]), 1) . '" r="4.5" fill="' . $c_site . '"/>'
        . '<g class="tip"><rect class="tipbg" x="' . round($tx, 1) . '" y="' . $mt . '" width="' . $tw . '" height="' . $th . '" rx="9"/>'
        . '<text class="tipt b" x="' . round($tx + 10, 1) . '" y="' . ($mt + 18) . '">' . esc_html($r) . '</text>'
        . '<text class="tipt" x="' . round($tx + 10, 1) . '" y="' . ($mt + 34) . '">Total: ' . (int) $tot_v[$i] . '</text>'
        . '<text class="tipt" x="' . round($tx + 10, 1) . '" y="' . ($mt + 50) . '">Site: ' . (int) $site_v[$i] . '</text></g></g>';
    }
    $svg .= '</svg>';
  }

  // ---- cartão "Comparar" ----
  $cmp  = $linha('📲', 'Total de escaneamentos', 'Média de ' . esc_html(number_format($media, 1, ',', '.')) . ' por dia', $geral, $delta($geral, $prev_total));
  $cmp .= $linha('🌐', 'Pelo site', esc_html(number_format($pct, 1, ',', '.')) . '% do total', $site_t, $delta($site_t, $prev_site));
  $cmp .= $linha('🏬', 'Outros canais', esc_html(number_format($geral ? 100 - $pct : 0, 1, ',', '.')) . '% do total', $outr_t, $delta($outr_t, $prev_outros));
  $cmp .= $linha('🏆', 'Melhor dia', esc_html($melhor['dia']), $melhor['n'], '<span class="dl nd">pico</span>');

  // ---- rosca em SVG: passe o mouse numa fatia (ou na legenda) para ver o detalhe no centro ----
  $pizza = function ($itens, $centro, $sub) {
    $tot = 0;
    foreach ($itens as $it) $tot += $it[1];
    if ($tot <= 0) return '<div class="vz">Sem dados no período</div>';
    $vis = [];
    foreach ($itens as $it) if ($it[1] > 0) $vis[] = $it;
    $gap  = count($vis) > 1 ? 0.5 : 0;
    $acum = 0;
    $k    = 0;
    $arcs = '';
    $leg  = '';
    foreach ($vis as $it) {
      $k++;
      $p     = $it[1] / $tot * 100;
      $curto = isset($it[3]) ? $it[3] : $it[0];
      $arcs .= '<g class="sl k' . $k . '"><circle cx="100" cy="100" r="70" pathLength="100" transform="rotate(-90 100 100)" stroke="' . esc_attr($it[2]) . '"'
        . ' stroke-dasharray="' . round($p - $gap, 3) . ' 100" stroke-dashoffset="-' . round($acum + $gap / 2, 3) . '"'
        . ' style="animation-delay:' . round(0.3 + $k * 0.12, 2) . 's"><title>' . esc_html($it[0]) . ': ' . (int) $it[1] . ' (' . number_format($p, 1, ',', '.') . '%)</title></circle>'
        . '<g class="ct"><text class="cn" x="100" y="82" text-anchor="middle">' . esc_html($curto) . '</text>'
        . '<text class="cv" x="100" y="108" text-anchor="middle">' . (int) $it[1] . '</text>'
        . '<text class="cp" x="100" y="126" text-anchor="middle">' . number_format($p, 1, ',', '.') . '%</text></g></g>';
      $acum += $p;
      $leg .= '<li class="k' . $k . '"><i style="background:' . esc_attr($it[2]) . '"></i><span>' . esc_html($it[0]) . '</span><b>'
        . (int) $it[1] . ' <small>' . number_format($p, 1, ',', '.') . '%</small></b>'
        . '<s><u style="width:' . round($p, 2) . '%;background:' . esc_attr($it[2]) . '"></u></s></li>';
    }
    return '<div class="pz"><div class="pw"><svg viewBox="0 0 200 200" role="img" aria-label="Gráfico de rosca">'
      . '<g class="cd"><text class="t1" x="100" y="106" text-anchor="middle">' . esc_html($centro) . '</text>'
      . '<text class="t2" x="100" y="124" text-anchor="middle">' . esc_html($sub) . '</text></g>'
      . $arcs . '</svg></div><ul class="lg">' . $leg . '</ul></div>';
  };
  $curtos = ['sem_origem' => 'Sem origem', 'site' => 'Site (QR)', 'site-selo' => 'Selos lojas', 'loja' => 'Loja',
             'impresso' => 'Impresso', 'campanha' => 'Campanha', 'outras' => 'Outras'];
  $it_origem = [];
  foreach ($tot_origem as $o => $n) {
    $it_origem[] = [isset($nomes[$o]) ? $nomes[$o] : $o, (int) $n, isset($cores[$o]) ? $cores[$o] : '#9AA3AD', isset($curtos[$o]) ? $curtos[$o] : $o];
  }
  $pz_site   = $pizza([['Site', $site_t, $c_site], ['Outros', $outr_t, $c_outros]], number_format($pct, 1, ',', '.') . '%', 'do site');
  $pz_origem = $pizza($it_origem, (string) $geral, 'total');
  $pz_os     = $pizza([['iOS', $tot_os['ios'], $c_os['ios']], ['Android', $tot_os['android'], $c_os['android']],
                       ['Outro (desktop/outros)', $tot_os['outro'], $c_os['outro'], 'Outro']], (string) $geral, 'total');

  // ---- tabela ----
  $linhas = '';
  foreach (array_reverse($dias, true) as $dia => $x) {
    $linhas .= '<tr><td>' . esc_html(wp_date('d/m/Y', strtotime($dia) + 43200)) . '</td>'
      . '<td>' . (int) $x['total'] . '</td><td>' . (int) $x['site'] . '</td><td>' . (int) $x['outros'] . '</td>'
      . '<td>' . ($x['total'] ? round($x['site'] / $x['total'] * 100) . '%' : '-') . '</td>'
      . '<td>' . (int) $x['ios'] . '</td><td>' . (int) $x['android'] . '</td><td>' . (int) $x['outro'] . '</td></tr>';
  }

  // ---- links ----
  $base_url = home_url('/');
  $btns  = '';
  $lista = ['7' => '7 dias', '30' => '30 dias', '90' => '90 dias', 'tudo' => 'Tudo'];
  foreach ($lista as $p => $txt) {
    $href  = esc_url(add_query_arg(['ver_contagem_qr' => 1, 'periodo' => $p, 'tema' => $tema], $base_url));
    $btns .= '<a class="b' . ((string) $periodo === (string) $p ? ' on' : '') . '" href="' . $href . '">' . esc_html($txt) . '</a>';
  }
  $args_tema = ['ver_contagem_qr' => 1, 'tema' => ($escuro ? 'claro' : 'escuro')];
  if ($periodo === 'custom') { $args_tema['de'] = $de; $args_tema['ate'] = $ate; } else { $args_tema['periodo'] = $periodo; }
  $ico_sol  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
  $ico_lua  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
  $rot_tema = $escuro ? 'Mudar para o tema claro' : 'Mudar para o tema escuro';
  $btns .= '<a class="b tm" href="' . esc_url(add_query_arg($args_tema, $base_url)) . '" title="' . esc_attr($rot_tema) . '" aria-label="' . esc_attr($rot_tema) . '">' . ($escuro ? $ico_sol : $ico_lua) . '</a>';

  $css = ':root{--bg:#0a0f0c;--card:#121a15;--card2:#161f19;--tx:#eef4f0;--mut:#8a9d90;--bd:#223027;--ln:#1c2820;--vd:#4CB574;--am:#F1CB0D;--sh:0 10px 30px rgba(0,0,0,.35);--up:#4ade80;--dn:#f87171;color-scheme:dark}html[data-tema=claro]{--bg:#eef3f0;--card:#fff;--card2:#f8fbf9;--tx:#1c2a21;--mut:#5b6b61;--bd:#d9e2dc;--ln:#e9efeb;--vd:#24723B;--am:#d4a900;--sh:0 10px 30px rgba(20,60,35,.10);--up:#16a34a;--dn:#dc2626;color-scheme:light}@property --n{syntax:"<integer>";inherits:false;initial-value:0}@property --p{syntax:"<percentage>";inherits:false;initial-value:0%}@keyframes up{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}@keyframes draw{from{stroke-dashoffset:1}to{stroke-dashoffset:0}}@keyframes fade{from{opacity:0}to{opacity:1}}@keyframes sweep{from{--p:0%}to{--p:100%}}@keyframes cnt{from{--n:0}}@keyframes wid{from{transform:scaleX(0)}to{transform:scaleX(1)}}@keyframes pop{from{opacity:0;transform:scale(.7)}to{opacity:1;transform:none}}*{box-sizing:border-box}body{margin:0;color:var(--tx);font:14.5px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:radial-gradient(900px 420px at 10% -10%,rgba(76,181,116,.13),transparent 60%),var(--bg);min-height:100vh}.w{max-width:1180px;margin:0 auto;padding:26px 18px 60px}.top{display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;margin-bottom:18px;animation:up .5s both}.ttl{display:flex;align-items:center;gap:12px}.logo{width:42px;height:42px;border-radius:13px;background:linear-gradient(135deg,#24723B,#4CB574);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:15px;box-shadow:0 6px 16px rgba(36,114,59,.45)}.ttl h1{margin:0;font-size:21px;letter-spacing:-.2px}.ttl p{margin:0;color:var(--mut);font-size:13px}.bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.b{padding:7px 14px;border-radius:999px;background:var(--card);color:var(--mut);text-decoration:none;border:1px solid var(--bd);font-weight:600;font-size:13px;transition:.2s}.b:hover{color:var(--tx);border-color:var(--vd)}.b.on{background:var(--tx);color:var(--bg);border-color:var(--tx)}.b.tm{width:36px;height:34px;padding:0;display:inline-flex;align-items:center;justify-content:center;color:var(--am)}.b.tm svg{width:18px;height:18px}.b.tm:hover{transform:rotate(18deg);color:var(--am);border-color:var(--am)}html[data-tema=claro] .b.tm{color:#5b6b61}form{display:flex;gap:6px;align-items:center;flex-wrap:wrap}form input{padding:6px 9px;border:1px solid var(--bd);border-radius:10px;font:inherit;font-size:13px;background:var(--card);color:var(--tx)}form span{color:var(--mut);font-size:13px}form button{padding:7px 15px;border:0;border-radius:10px;background:var(--am);color:#1a1a00;font-weight:800;font-size:13px;cursor:pointer}.main{display:grid;grid-template-columns:minmax(0,1.85fr) minmax(290px,1fr);gap:14px;margin-bottom:14px}.card{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--bd);border-radius:18px;padding:20px 22px;box-shadow:var(--sh);animation:up .6s both}.main .card:nth-child(1){animation-delay:.08s}.main .card:nth-child(2){animation-delay:.16s}.ch{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:10px}.card h2{margin:0;font-size:15px;font-weight:700}.leg{display:flex;gap:16px;font-size:12.5px;color:var(--mut)}.leg i{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px}.chart{width:100%;height:auto;display:block;overflow:visible}.gl{stroke:var(--ln);stroke-width:1;stroke-dasharray:3 5}.ax{fill:var(--mut);font-size:11px}.ln{fill:none;stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:1;stroke-dashoffset:1;animation:draw 1.8s .3s cubic-bezier(.3,.7,.2,1) forwards}.ar{opacity:0;animation:fade 1.2s 1.2s forwards}.hv rect.hit{fill:transparent}.hv:hover rect.hit{fill:rgba(255,255,255,.05)}html[data-tema=claro] .hv:hover rect.hit{fill:rgba(0,0,0,.05)}.hv .tip{opacity:0;pointer-events:none;transition:.12s}.hv:hover .tip{opacity:1}.hv .dt{opacity:0;transition:.12s}.hv:hover .dt{opacity:1}.tipbg{fill:var(--tx)}.tipt{fill:var(--bg);font-size:11.5px}.tipt.b{font-weight:700}.cmp h2{margin-bottom:6px}.row{display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--ln);animation:up .5s both}.row:nth-of-type(1){animation-delay:.3s}.row:nth-of-type(2){animation-delay:.38s}.row:nth-of-type(3){animation-delay:.46s}.row:nth-of-type(4){animation-delay:.54s}.row .ic{width:34px;height:34px;flex:none;border-radius:11px;display:flex;align-items:center;justify-content:center;background:rgba(76,181,116,.14);font-size:16px}.row .lb{flex:1;min-width:0}.row .lb b{display:block;font-size:13.5px;font-weight:600}.row .lb small{color:var(--mut);font-size:12px}.row .vl{text-align:right}.row .vl strong{display:block;font-size:24px;line-height:1.1;font-weight:800}.dl{font-size:12px;font-weight:600}.dl.up{color:var(--up)}.dl.dn{color:var(--dn)}.dl.nd{color:var(--mut);font-weight:500}.foot{margin-top:12px;color:var(--mut);font-size:12px}.num{counter-reset:n var(--n);animation:cnt 1.4s cubic-bezier(.2,.7,.2,1) both}.num::after{content:counter(n)}.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:14px;margin-bottom:14px}.g .card:nth-child(1){animation-delay:.3s}.g .card:nth-child(2){animation-delay:.4s}.g .card:nth-child(3){animation-delay:.5s}.pz{display:flex;flex-direction:column;align-items:center;gap:18px;margin-top:8px}.pw{position:relative;width:196px;height:196px}.lg{list-style:none;margin:0;padding:0;width:100%;font-size:13px}.lg li{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:7px 6px;border-radius:10px;transition:.2s}.lg li:hover{background:rgba(76,181,116,.09)}.lg i{width:10px;height:10px;border-radius:50%;flex:none}.lg span{flex:1}.lg b{font-weight:700}.lg b small{color:var(--mut);font-weight:500}.lg s{flex-basis:100%;height:4px;border-radius:4px;background:var(--ln);overflow:hidden}.lg u{display:block;height:100%;border-radius:4px;transform-origin:left;animation:wid 1s .7s cubic-bezier(.2,.7,.2,1) both}@keyframes dr{from{stroke-dasharray:0 100}}.pw svg{width:100%;height:100%;display:block;overflow:visible}.pw text{fill:var(--tx);font-family:inherit;pointer-events:none}.sl circle{fill:none;stroke-width:28;animation:dr 1.2s cubic-bezier(.2,.7,.2,1) both;transition:stroke-width .2s,opacity .2s,filter .2s;cursor:pointer;pointer-events:stroke}.sl:hover circle{stroke-width:33;filter:brightness(1.15)}.pz:has(.sl:hover) .sl:not(:hover) circle{opacity:.38}.cd{animation:pop .6s 1s backwards;transition:opacity .15s}.cd .t1{font-size:27px;font-weight:800}.cd .t2{font-size:11px;fill:var(--mut)}.ct{opacity:0;transition:opacity .15s}.sl:hover .ct{opacity:1}.ct .cn{font-size:11px;fill:var(--mut)}.ct .cv{font-size:27px;font-weight:800}.ct .cp{font-size:12.5px;font-weight:700;fill:var(--vd)}.pz:has(.sl:hover) .cd,.pz:has(li:hover) .cd{opacity:0}.pz:has(.sl.k1:hover) li.k1,.pz:has(li.k1:hover) li.k1{background:rgba(76,181,116,.15)}.pz:has(li.k1:hover) .sl.k1 circle{stroke-width:33;filter:brightness(1.15)}.pz:has(li.k1:hover) .sl.k1 .ct{opacity:1}.pz:has(li.k1:hover) .sl:not(.k1) circle{opacity:.38}.pz:has(.sl.k2:hover) li.k2,.pz:has(li.k2:hover) li.k2{background:rgba(76,181,116,.15)}.pz:has(li.k2:hover) .sl.k2 circle{stroke-width:33;filter:brightness(1.15)}.pz:has(li.k2:hover) .sl.k2 .ct{opacity:1}.pz:has(li.k2:hover) .sl:not(.k2) circle{opacity:.38}.pz:has(.sl.k3:hover) li.k3,.pz:has(li.k3:hover) li.k3{background:rgba(76,181,116,.15)}.pz:has(li.k3:hover) .sl.k3 circle{stroke-width:33;filter:brightness(1.15)}.pz:has(li.k3:hover) .sl.k3 .ct{opacity:1}.pz:has(li.k3:hover) .sl:not(.k3) circle{opacity:.38}.pz:has(.sl.k4:hover) li.k4,.pz:has(li.k4:hover) li.k4{background:rgba(76,181,116,.15)}.pz:has(li.k4:hover) .sl.k4 circle{stroke-width:33;filter:brightness(1.15)}.pz:has(li.k4:hover) .sl.k4 .ct{opacity:1}.pz:has(li.k4:hover) .sl:not(.k4) circle{opacity:.38}.pz:has(.sl.k5:hover) li.k5,.pz:has(li.k5:hover) li.k5{background:rgba(76,181,116,.15)}.pz:has(li.k5:hover) .sl.k5 circle{stroke-width:33;filter:brightness(1.15)}.pz:has(li.k5:hover) .sl.k5 .ct{opacity:1}.pz:has(li.k5:hover) .sl:not(.k5) circle{opacity:.38}.pz:has(.sl.k6:hover) li.k6,.pz:has(li.k6:hover) li.k6{background:rgba(76,181,116,.15)}.pz:has(li.k6:hover) .sl.k6 circle{stroke-width:33;filter:brightness(1.15)}.pz:has(li.k6:hover) .sl.k6 .ct{opacity:1}.pz:has(li.k6:hover) .sl:not(.k6) circle{opacity:.38}.pz:has(.sl.k7:hover) li.k7,.pz:has(li.k7:hover) li.k7{background:rgba(76,181,116,.15)}.pz:has(li.k7:hover) .sl.k7 circle{stroke-width:33;filter:brightness(1.15)}.pz:has(li.k7:hover) .sl.k7 .ct{opacity:1}.pz:has(li.k7:hover) .sl:not(.k7) circle{opacity:.38}.pz:has(.sl.k8:hover) li.k8,.pz:has(li.k8:hover) li.k8{background:rgba(76,181,116,.15)}.pz:has(li.k8:hover) .sl.k8 circle{stroke-width:33;filter:brightness(1.15)}.pz:has(li.k8:hover) .sl.k8 .ct{opacity:1}.pz:has(li.k8:hover) .sl:not(.k8) circle{opacity:.38}.vz{padding:60px 0;text-align:center;color:var(--mut)}table{width:100%;border-collapse:collapse;font-size:13.5px}th,td{padding:10px 10px;text-align:right;border-bottom:1px solid var(--ln)}th:first-child,td:first-child{text-align:left}th{color:var(--mut);font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.5px;position:sticky;top:0;background:var(--card)}tbody tr{transition:.15s}tbody tr:hover{background:rgba(76,181,116,.08)}.tw{max-height:380px;overflow:auto;margin-top:8px}.nota{margin-top:16px;color:var(--mut);font-size:12px}@media (max-width:900px){.main{grid-template-columns:1fr}}@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}.ln{stroke-dashoffset:0}.ar{opacity:1}}';

  $html  = '<!doctype html><html lang="pt-BR" data-tema="' . esc_attr($tema) . '"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">'
    . '<title>Contagem do QR code</title><style>' . $css . '</style></head><body><div class="w">'
    . '<div class="top"><div class="ttl"><div class="logo">QQ</div><div><h1>Contagem do QR code</h1>'
    . '<p>Período: ' . esc_html(wp_date('d/m/Y', strtotime($de) + 43200)) . ' a ' . esc_html(wp_date('d/m/Y', strtotime($ate) + 43200)) . '</p></div></div>'
    . '<div class="bar">' . $btns
    . '<form method="get" action="' . esc_url($base_url) . '">'
    . '<input type="hidden" name="ver_contagem_qr" value="1"><input type="hidden" name="tema" value="' . esc_attr($tema) . '">'
    . '<input type="date" name="de" value="' . esc_attr($de) . '"><span>até</span>'
    . '<input type="date" name="ate" value="' . esc_attr($ate) . '"><button type="submit">Filtrar</button></form></div></div>'
    . '<div class="main"><div class="card"><div class="ch"><h2>Escaneamentos por dia</h2><div class="leg">'
    . '<span><i style="background:' . $c_total . '"></i>Total</span><span><i style="background:' . $c_site . '"></i>Pelo site</span></div></div>'
    . ($svg !== '' ? $svg : '<div class="vz">Sem dados no período</div>') . '</div>'
    . '<div class="card cmp"><h2>Comparar</h2>' . $cmp
    . ($tem_prev ? '<div class="foot">vs. ' . $rot_prev . ' (período anterior)</div>' : '') . '</div></div>'
    . '<div class="g"><div class="card"><h2>Site x Outros</h2>' . $pz_site . '</div>'
    . '<div class="card"><h2>Por origem</h2>' . $pz_origem . '</div>'
    . '<div class="card"><h2>Por sistema</h2>' . $pz_os . '</div></div>'
    . '<div class="card"><h2>Detalhe por dia</h2><div class="tw"><table><thead><tr><th>Dia</th><th>Total</th><th>Site</th><th>Outros</th><th>% site</th><th>iOS</th><th>Android</th><th>Outro</th></tr></thead><tbody>'
    . $linhas . '</tbody></table></div></div>'
    . '<p class="nota">"Site" soma o QR do site e os selos das lojas. "Outros" inclui loja física, material impresso, campanhas e QRs antigos sem origem. A divisão por origem só existe desde a troca do QR; antes disso os acessos entram como "sem origem".</p>'
    . '</div></body></html>';

  nocache_headers();
  header('Content-Type: text/html; charset=utf-8');
  echo $html;
  exit;
}, 0);
