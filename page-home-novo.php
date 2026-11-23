// Painel de contagem do QR (só administrador logado)
// Acesse: queroqueropag.com.br/home/?ver_contagem_qr=1
// Substitui o bloco "2) Ver a contagem". O bloco 1 (redirecionamento) continua igual.
add_action('template_redirect', function () {
  if (!isset($_GET['ver_contagem_qr']) || !current_user_can('manage_options')) return;

  $dados = get_option('contagem_abrir_app', []);
  ksort($dados);

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
    $n   = ($periodo === '7') ? 7 : (($periodo === '90') ? 90 : 30);
    $periodo = (string) $n;
    $de  = wp_date('Y-m-d', time() - ($n - 1) * DAY_IN_SECONDS);
    $ate = $hoje;
  }

  $nomes = [
    'site'       => 'Site (QR)',
    'site-selo'  => 'Site (selos das lojas)',
    'loja'       => 'Loja física',
    'impresso'   => 'Material impresso',
    'campanha'   => 'Campanha',
    'outras'     => 'Outras origens',
    'sem_origem' => 'Sem origem (QRs antigos)',
  ];
  $cores = [
    'site'       => '#24723B',
    'site-selo'  => '#6BAF7E',
    'loja'       => '#F1CB0D',
    'impresso'   => '#E08A1E',
    'campanha'   => '#3B82C4',
    'outras'     => '#8A8F98',
    'sem_origem' => '#C9CED6',
  ];

  // ---- agrega os dados do período ----
  $dias       = [];
  $tot_origem = [];
  $tot_os     = ['ios' => 0, 'android' => 0, 'outro' => 0];

  $t_ini = strtotime($de . ' 12:00:00');
  $t_fim = strtotime($ate . ' 12:00:00');
  for ($t = $t_ini, $i = 0; $t && $t <= $t_fim && $i < 400; $t += DAY_IN_SECONDS, $i++) {
    $dia = wp_date('Y-m-d', $t);
    $d   = $dados[$dia] ?? [];

    $ios     = (int) ($d['ios'] ?? 0);
    $android = (int) ($d['android'] ?? 0);
    $outro   = (int) ($d['outro'] ?? 0);
    $total   = $ios + $android + $outro;

    $orig  = (array) ($d['origem'] ?? []);
    $soma  = array_sum(array_map('intval', $orig));
    if ($total > $soma) $orig['sem_origem'] = (int) ($orig['sem_origem'] ?? 0) + ($total - $soma);

    $site = (int) ($orig['site'] ?? 0) + (int) ($orig['site-selo'] ?? 0);

    $dias[$dia] = ['total' => $total, 'site' => $site, 'outros' => $total - $site,
                   'ios' => $ios, 'android' => $android, 'outro' => $outro];

    foreach ($orig as $o => $n) $tot_origem[$o] = ($tot_origem[$o] ?? 0) + (int) $n;
    $tot_os['ios'] += $ios; $tot_os['android'] += $android; $tot_os['outro'] += $outro;
  }

  $geral  = array_sum(array_column($dias, 'total'));
  $site_t = array_sum(array_column($dias, 'site'));
  $outr_t = $geral - $site_t;
  $pct    = $geral ? round($site_t / $geral * 100, 1) : 0;
  $media  = count($dias) ? round($geral / count($dias), 1) : 0;

  // ---- dados para os gráficos ----
  $pizza_origem = ['labels' => [], 'data' => [], 'colors' => []];
  foreach ($tot_origem as $o => $n) {
    if ($n <= 0) continue;
    $pizza_origem['labels'][] = $nomes[$o] ?? $o;
    $pizza_origem['data'][]   = $n;
    $pizza_origem['colors'][] = $cores[$o] ?? '#8A8F98';
  }
  $js = [
    'origem' => $pizza_origem,
    'site'   => ['labels' => ['Site', 'Outros'], 'data' => [$site_t, $outr_t], 'colors' => ['#24723B', '#C9CED6']],
    'os'     => ['labels' => ['iOS', 'Android', 'Outro (desktop/outros)'],
                 'data'   => [$tot_os['ios'], $tot_os['android'], $tot_os['outro']],
                 'colors' => ['#142B1C', '#24723B', '#F1CB0D']],
    'dias'   => [
      'labels' => array_map(function ($d) { return substr($d, 8, 2) . '/' . substr($d, 5, 2); }, array_keys($dias)),
      'site'   => array_column($dias, 'site'),
      'outros' => array_column($dias, 'outros'),
    ],
  ];

  $url = function ($args) { return esc_url(add_query_arg(array_merge(['ver_contagem_qr' => 1], $args), home_url('/'))); };
  $btn = function ($p, $txt) use ($periodo, $url) {
    return '<a class="b' . ($periodo === $p ? ' on' : '') . '" href="' . $url(['periodo' => $p]) . '">' . esc_html($txt) . '</a>';
  };

  nocache_headers();
  header('Content-Type: text/html; charset=utf-8');
  ?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Contagem do QR code</title>
<style>
:root{--bg:#f3f5f4;--card:#fff;--tx:#1c2a21;--mut:#5b6b61;--bd:#cfd8d2;--ln:#edf0ee;--vd:#24723B;--am:#F1CB0D;--amtx:#142b1c;--sh:0 1px 3px rgba(0,0,0,.06)}
:root[data-tema="dark"]{--bg:#0e1411;--card:#17201b;--tx:#e6efe9;--mut:#93a399;--bd:#2c3a32;--ln:#243029;--vd:#5CC27E;--am:#F1CB0D;--amtx:#142b1c;--sh:0 1px 3px rgba(0,0,0,.5);color-scheme:dark}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
.w{max-width:1100px;margin:0 auto;padding:24px 16px 48px}
h1{margin:0 0 4px;font-size:24px;color:var(--vd)}.sub{margin:0 0 18px;color:var(--mut)}
.bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:18px}
.b{padding:7px 14px;border-radius:999px;background:var(--card);color:var(--vd);text-decoration:none;border:1px solid var(--bd);font-weight:600;font-size:14px;cursor:pointer;font-family:inherit}
.b.on{background:var(--vd);color:var(--bg);border-color:var(--vd)}
form{display:flex;gap:6px;align-items:center;margin-left:auto;flex-wrap:wrap}
form input{padding:6px 8px;border:1px solid var(--bd);border-radius:8px;font:inherit;background:var(--card);color:var(--tx)}
form button{padding:7px 14px;border:0;border-radius:8px;background:var(--am);color:var(--amtx);font-weight:700;cursor:pointer}
.k{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:18px}
.c{background:var(--card);border-radius:14px;padding:16px 18px;box-shadow:var(--sh)}
.c small{display:block;color:var(--mut);font-size:13px}.c b{display:block;font-size:30px;line-height:1.2;color:var(--vd)}
.c.y b{color:var(--tx)}.c.y{border-left:5px solid var(--am)}
.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;margin-bottom:12px}
.card{background:var(--card);border-radius:14px;padding:16px 18px;box-shadow:var(--sh)}
.card h2{margin:0 0 10px;font-size:16px;color:var(--vd)}.cv{position:relative;height:260px}.cv.t{height:300px}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:7px 8px;text-align:right;border-bottom:1px solid var(--ln)}
th:first-child,td:first-child{text-align:left}th{color:var(--mut);font-weight:600;position:sticky;top:0;background:var(--card)}
.tw{max-height:380px;overflow:auto}.nota{margin-top:14px;color:var(--mut);font-size:13px}
.vazio{padding:50px 0;text-align:center;color:var(--mut)}
</style>
<script>try{var t=localStorage.getItem('qq_tema');if(!t)t=matchMedia('(prefers-color-scheme: light)').matches?'light':'dark';document.documentElement.setAttribute('data-tema',t)}catch(e){document.documentElement.setAttribute('data-tema','dark')}</script></head><body><div class="w">
<h1>Contagem do QR code</h1>
<p class="sub">Período: <?php echo esc_html(wp_date('d/m/Y', strtotime($de))); ?> a <?php echo esc_html(wp_date('d/m/Y', strtotime($ate))); ?></p>

<div class="bar">
  <?php echo $btn('7', 'Últimos 7 dias') . $btn('30', 'Últimos 30 dias') . $btn('90', 'Últimos 90 dias') . $btn('tudo', 'Tudo'); ?>
  <button type="button" class="b" id="tema" aria-label="Alternar tema">🌓 Tema</button>
  <form method="get" action="<?php echo esc_url(home_url('/')); ?>">
    <input type="hidden" name="ver_contagem_qr" value="1">
    <input type="date" name="de" value="<?php echo esc_attr($de); ?>">
    <span>até</span>
    <input type="date" name="ate" value="<?php echo esc_attr($ate); ?>">
    <button type="submit">Filtrar</button>
  </form>
</div>

<div class="k">
  <div class="c"><small>Total de escaneamentos</small><b><?php echo (int) $geral; ?></b></div>
  <div class="c y"><small>Pelo site</small><b><?php echo (int) $site_t; ?></b></div>
  <div class="c"><small>Outros canais</small><b><?php echo (int) $outr_t; ?></b></div>
  <div class="c y"><small>% do site</small><b><?php echo esc_html(number_format($pct, 1, ',', '.')); ?>%</b></div>
  <div class="c"><small>Média por dia</small><b><?php echo esc_html(number_format($media, 1, ',', '.')); ?></b></div>
</div>

<?php if ($geral > 0) : ?>
<div class="g">
  <div class="card"><h2>Site x Outros</h2><div class="cv"><canvas id="c-site"></canvas></div></div>
  <div class="card"><h2>Por origem</h2><div class="cv"><canvas id="c-origem"></canvas></div></div>
  <div class="card"><h2>Por sistema</h2><div class="cv"><canvas id="c-os"></canvas></div></div>
</div>
<div class="card" style="margin-bottom:12px"><h2>Por dia</h2><div class="cv t"><canvas id="c-dias"></canvas></div></div>

<div class="card"><h2>Detalhe por dia</h2><div class="tw"><table>
<thead><tr><th>Dia</th><th>Total</th><th>Site</th><th>Outros</th><th>iOS</th><th>Android</th><th>Outro</th></tr></thead><tbody>
<?php foreach (array_reverse($dias, true) as $dia => $x) : ?>
<tr><td><?php echo esc_html(wp_date('d/m/Y', strtotime($dia))); ?></td><td><?php echo (int) $x['total']; ?></td><td><?php echo (int) $x['site']; ?></td><td><?php echo (int) $x['outros']; ?></td><td><?php echo (int) $x['ios']; ?></td><td><?php echo (int) $x['android']; ?></td><td><?php echo (int) $x['outro']; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
var D = <?php echo wp_json_encode($js); ?>;
var charts = [];
var MAPA_ESCURO = { '#24723B': '#4CB574', '#142B1C': '#E6EFE9', '#C9CED6': '#5B6770', '#8A8F98': '#9AA3AD' };
function escuro() { return document.documentElement.getAttribute('data-tema') === 'dark'; }
function css(v) { return getComputedStyle(document.documentElement).getPropertyValue(v).trim(); }
function cor(c) { return escuro() && MAPA_ESCURO[c.toUpperCase()] ? MAPA_ESCURO[c.toUpperCase()] : c; }
function pizza(id, o) {
  return new Chart(document.getElementById(id), {
    type: 'doughnut',
    data: { labels: o.labels, datasets: [{ data: o.data, backgroundColor: o.colors.map(cor), borderWidth: 2, borderColor: css('--card') }] },
    options: { maintainAspectRatio: false, cutout: '55%',
      plugins: { legend: { position: 'bottom', labels: { color: css('--tx') } },
        tooltip: { callbacks: { label: function (c) {
          var t = c.dataset.data.reduce(function (a, b) { return a + b; }, 0);
          return c.label + ': ' + c.parsed + ' (' + (t ? (c.parsed / t * 100).toFixed(1).replace('.', ',') : 0) + '%)';
        } } } } }
  });
}
function barras() {
  var tx = css('--tx'), ln = css('--ln');
  return new Chart(document.getElementById('c-dias'), {
    type: 'bar',
    data: { labels: D.dias.labels, datasets: [
      { label: 'Site', data: D.dias.site, backgroundColor: cor('#24723B') },
      { label: 'Outros', data: D.dias.outros, backgroundColor: cor('#C9CED6') } ] },
    options: { maintainAspectRatio: false,
      scales: { x: { stacked: true, ticks: { color: tx }, grid: { color: ln } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: tx }, grid: { color: ln } } },
      plugins: { legend: { position: 'bottom', labels: { color: tx } } } }
  });
}
function desenhar() {
  charts.forEach(function (c) { c.destroy(); });
  charts = [pizza('c-site', D.site), pizza('c-origem', D.origem), pizza('c-os', D.os), barras()];
}
desenhar();
</script>
<?php else : ?>
<div class="card vazio">Nenhum escaneamento registrado neste período.</div>
<?php endif; ?>

<p class="nota">"Site" soma o QR do site e os selos das lojas. "Outros" inclui loja física, material impresso, campanhas e QRs antigos sem origem. A divisão por origem só existe desde a troca do QR; antes disso os acessos entram como "sem origem".</p>
<script>
document.getElementById('tema').addEventListener('click', function () {
  var atual = document.documentElement.getAttribute('data-tema');
  var novo = atual === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-tema', novo);
  try { localStorage.setItem('qq_tema', novo); } catch (e) {}
  if (typeof desenhar === 'function') desenhar();
});
</script>
</div></body></html>
<?php
  exit;
}, 0);
