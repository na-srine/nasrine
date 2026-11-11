<?php
require 'config.php';
if (empty($_SESSION['admin'])) { header('Location: admin.php'); exit; }
$statuts = ['nouvelle'=>'Nouvelle','en_preparation'=>'En préparation','en_livraison'=>'En livraison','livree'=>'Livrée','annulee'=>'Annulée'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'statut' && isset($statuts[$_POST['statut'] ?? '']))
        db()->prepare('UPDATE commandes SET statut=? WHERE id=?')->execute([$_POST['statut'], (int)$_POST['id']]);
    header('Location: commandes.php'); exit;
}

$commandes = db()->query('SELECT * FROM commandes ORDER BY id DESC LIMIT 50')->fetchAll();
$st = db()->prepare('SELECT nom, quantite FROM commandes_plats WHERE commande_id=?');
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Commandes — Administration</title><link rel="stylesheet" href="style.css"></head><body class="admin">
<header><h1>Commandes</h1><a href="admin.php" class="retour">← Retour à l'administration</a></header>
<main class="liste-cmds">
  <?php foreach ($commandes as $c): $st->execute([$c['id']]); ?>
    <form method="post" class="cmd s-<?= h($c['statut']) ?>">
      <div><strong>n°<?= $c['id'] ?> · <?= h($c['client_nom']) ?></strong> <span><?= h($c['created_at']) ?></span>
        <p><?= h($c['telephone']) ?> — <?= h($c['adresse']) ?><?= $c['note'] ? ' (' . h($c['note']) . ')' : '' ?></p>
        <ul><?php foreach ($st->fetchAll() as $l): ?><li><?= $l['quantite'] ?> × <?= h($l['nom']) ?></li><?php endforeach; ?></ul>
        <b><?= fcfa($c['total']) ?></b></div>
      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= $c['id'] ?>">
      <select name="statut"><?php foreach ($statuts as $k => $v): ?><option value="<?= $k ?>" <?= $k === $c['statut'] ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
      <button name="action" value="statut">Mettre à jour</button>
    </form>
  <?php endforeach; if (!$commandes) echo '<p class="vide">Aucune commande pour le moment.</p>'; ?>
</main>
</body></html>