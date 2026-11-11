<?php
require 'config.php';
$statuts = ['nouvelle'=>'Commande reçue','en_preparation'=>'En préparation','en_livraison'=>'En livraison','livree'=>'Livrée'];
$erreur = '';

if (isset($_GET['autre'])) { unset($_SESSION['suivi']); header('Location: suivi.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $num = (int)($_POST['num'] ?? 0);
    $tel = preg_replace('/\D/', '', $_POST['tel'] ?? '');
    $st = db()->prepare('SELECT id, telephone FROM commandes WHERE id=?');
    $st->execute([$num]);
    $c = $st->fetch();
    if ($c && $tel !== '' && preg_replace('/\D/', '', $c['telephone']) === $tel) {
        $_SESSION['suivi'] = (int)$c['id'];
        header('Location: suivi.php'); exit;
    }
    $erreur = 'Numéro de commande ou téléphone incorrect.';
}

$commande = null; $lignes = [];
if (!empty($_SESSION['suivi'])) {
    $st = db()->prepare('SELECT * FROM commandes WHERE id=?');
    $st->execute([(int)$_SESSION['suivi']]);
    $commande = $st->fetch() ?: null;
    if ($commande) {
        $st = db()->prepare('SELECT nom, quantite FROM commandes_plats WHERE commande_id=?');
        $st->execute([$commande['id']]);
        $lignes = $st->fetchAll();
    }
}
$cles = array_keys($statuts);
$pos = $commande ? array_search($commande['statut'], $cles, true) : false;
$termine = $commande && in_array($commande['statut'], ['livree', 'annulee'], true);
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php if ($commande && !$termine): ?><meta http-equiv="refresh" content="30"><?php endif; ?>
<title>Suivre ma commande — Chez Maman</title><link rel="stylesheet" href="style.css"></head>
<body>
<header><h1>Chez Maman</h1><p>Cuisine béninoise, livrée chaude.</p></header>
<main class="suivi">
  <p><a href="index.php">← Retour au menu</a></p>
  <h2>Suivre ma commande</h2>

<?php if ($commande): ?>
  <?php if (isset($_GET['ok'])): ?>
    <p class="ok">Merci ! Votre commande est enregistrée. Notez bien votre numéro : <b>n°<?= (int)$commande['id'] ?></b>.</p>
  <?php endif; ?>

  <p><strong>Commande n°<?= (int)$commande['id'] ?></strong> · <?= h($commande['client_nom']) ?></p>

  <?php if ($commande['statut'] === 'annulee'): ?>
    <p class="err">Cette commande a été annulée. Appelez-nous pour plus d'informations.</p>
  <?php elseif ($pos !== false): ?>
    <ol class="etapes">
      <?php foreach ($cles as $i => $k): ?>
        <li class="<?= $i < $pos ? 'fait' : ($i === $pos ? 'actuel' : '') ?>"><?= h($statuts[$k]) ?></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <ul><?php foreach ($lignes as $l): ?><li><?= (int)$l['quantite'] ?> × <?= h($l['nom']) ?></li><?php endforeach; ?></ul>
  <p>Total : <b><?= fcfa($commande['total']) ?></b> · paiement à la livraison</p>
  <?php if (!$termine): ?><p><small>Cette page se met à jour automatiquement.</small></p><?php endif; ?>
  <p><a href="suivi.php?autre=1">Suivre une autre commande</a></p>

<?php else: ?>
  <?php if ($erreur): ?><p class="err"><?= h($erreur) ?></p><?php endif; ?>
  <form method="post" class="envoi">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Numéro de commande<input name="num" type="number" min="1" required></label>
    <label>Téléphone utilisé pour la commande<input name="tel" type="tel" required></label>
    <button class="primaire">Voir ma commande</button>
  </form>
<?php endif; ?>
</main>
</body></html>