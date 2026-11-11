<?php
require 'config.php';
$statuts = ['nouvelle'=>'Nouvelle','en_preparation'=>'En préparation','en_livraison'=>'En livraison','livree'=>'Livrée','annulee'=>'Annulée'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'login') {
        if (hash_equals(ADMIN_PASSWORD, $_POST['mdp'] ?? '')) { session_regenerate_id(true); $_SESSION['admin'] = true; }
        else $err = 'Mot de passe incorrect.';
    } elseif (!empty($_SESSION['admin'])) {
        if ($a === 'statut' && isset($statuts[$_POST['statut'] ?? '']))
            db()->prepare('UPDATE commandes SET statut=? WHERE id=?')->execute([$_POST['statut'], (int)$_POST['id']]);
        if ($a === 'plat' && trim($_POST['nom']) !== '' && (int)$_POST['prix'] > 0) {
            $img = null;
            $f = $_FILES['image'] ?? null;
            if ($f && $f['error'] === UPLOAD_ERR_OK && $f['size'] <= 3 * 1024 * 1024) {
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][mime_content_type($f['tmp_name'])] ?? null;
                if ($ext) {
                    $img = bin2hex(random_bytes(8)) . '.' . $ext;
                    move_uploaded_file($f['tmp_name'], __DIR__ . '/images/' . $img);
                }
            }
            db()->prepare('INSERT INTO plats (nom, description, prix, image) VALUES (?,?,?,?)')
                ->execute([trim($_POST['nom']), trim($_POST['description']), (int)$_POST['prix'], $img]);
        }
        if ($a === 'dispo') db()->prepare('UPDATE plats SET disponible = 1 - disponible WHERE id=?')->execute([(int)$_POST['id']]);
        if ($a === 'logout') { unset($_SESSION['admin']); }
    }
    if ($a !== 'login' || !empty($_SESSION['admin'])) { header('Location: admin.php'); exit; }
}
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Administration</title><link rel="stylesheet" href="style.css"></head><body class="admin">
<?php if (empty($_SESSION['admin'])): ?>
  <form method="post" class="envoi login"><h1>Administration</h1>
    <?php if (!empty($err)): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Mot de passe<input type="password" name="mdp" required autofocus></label>
    <button name="action" value="login" class="primaire">Se connecter</button></form>
<?php else:
  $plats = db()->query('SELECT * FROM plats ORDER BY nom')->fetchAll(); ?>
  <header><h1>Administration</h1>
    <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><button name="action" value="logout">Se déconnecter</button></form></header>
    <main class="adm">
  <aside class="gauche"><h2>Menu</h2>
    <?php foreach ($plats as $p): ?>
      <form method="post" class="ligne"><span><?= h($p['nom']) ?> · <?= fcfa($p['prix']) ?></span>
        <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button name="action" value="dispo"><?= $p['disponible'] ? 'Retirer' : 'Remettre' ?></button></form>
    <?php endforeach; ?>
    <form id="form-plat" method="post" class="envoi" enctype="multipart/form-data"><h2>Nouveau plat</h2><input type="hidden" name="csrf" value="<?= csrf() ?>">
      <label>Nom<input name="nom" required></label>
      <label>Description<input name="description"></label>
      <label>Prix (FCFA)<input name="prix" type="number" min="1" required></label>
      <label>Catégorie
        <select name="categorie"><option value="repas">Repas</option><option value="boisson">Jus</option></select></label>
      <label>Photo (JPG, PNG ou WebP, 3 Mo max)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
     </form>
  </aside>
  </main>
 <div class="barre-boutons">
  <button type="submit" form="form-plat" name="action" value="plat" class="btn-commande">Ajouter au menu</button>
  <a href="commandes.php" class="btn-commande btn-suivi">Voir les commandes</a>
</div>
<?php endif; ?></body></html>