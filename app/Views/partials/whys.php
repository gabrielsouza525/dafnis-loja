<?php
/** Diferenciais da loja (home e Sobre nós). */
$whys = [
    ['title' => 'Conteúdo profissional', 'text' => 'Conteúdos organizados de forma clara, objetiva e aplicada à rotina de trabalho.', 'icon' => 'clipcheck'],
    ['title' => 'Plataforma online', 'text' => 'Estude pelo computador ou celular, no seu ritmo.', 'icon' => 'monitor'],
    ['title' => 'Acesso fácil', 'text' => 'Encontre e acesse seus treinamentos em poucos cliques.', 'icon' => 'search'],
    ['title' => 'Certificação', 'text' => 'Certificado de conclusão ao finalizar o treinamento, conforme as regras de cada curso.', 'icon' => 'award'],
    ['title' => 'Treinamentos para empresas', 'text' => 'Compra de vagas para equipes e atendimento dedicado.', 'icon' => 'building'],
    ['title' => 'Catálogo diversificado', 'text' => 'NRs, cursos complementares, jogos e simuladores reunidos em um só lugar.', 'icon' => 'briefcase'],
];
?>
<div class="why reveal">
<?php foreach ($whys as $w): ?>
<div class="why-item"><span class="why-ic"><?= icon($w['icon']) ?></span><div><h3><?= e($w['title']) ?></h3><p><?= e($w['text']) ?></p></div></div>
<?php endforeach; ?>
</div>
