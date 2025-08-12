<?php
require_once 'php/config.php';
$numserie = $_GET['numserie'] ?? '';
// Handle send to repair POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_repair']) && isset($_POST['numserie'])) {
    $numserie = $_POST['numserie'];
    $date_sent = date('Y-m-d H:i:s');
    // Fetch the material
    $stmt = $pdo->prepare("SELECT * FROM materiel WHERE NumSerie = ?");
    $stmt->execute([$numserie]);
    $mat = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mat) {
        $insert = $pdo-> prepare("INSERT INTO materiel_repair_history (NumSerie ,CodeMarque, CodeType, Model, CodeUtilisateur, Dateentree, stock, observation, Processeur, memoire, disqdur, graphique, pouce, ecran, mhtz, mo, ip, classification, STE, CodeFournisseur, damage_cause, date_sent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([
            $mat['NumSerie'], $mat['CodeMarque'], $mat['CodeType'], $mat['Model'], $mat['CodeUtilisateur'], $mat['Dateentree'], $mat['stock'], $mat['observation'], $mat['Processeur'], $mat['memoire'], $mat['disqdur'], $mat['graphique'], $mat['pouce'], $mat['ecran'], $mat['mhtz'], $mat['mo'], $mat['ip'], $mat['classification'], $mat['STE'], $mat['CodeFournisseur'], $mat['damage_cause'], $date_sent
        ]);
        // Only use columns that exist in materiel_en_reparation
        $fields = [
            'NumSerie', 'CodeMarque', 'CodeType', 'Model', 'CodeUtilisateur', 'Dateentree',
            'stock', 'observation', 'Processeur', 'memoire', 'disqdur', 'graphique',
            'pouce', 'ecran', 'mhtz', 'mo', 'ip', 'classification', 'STE', 'CodeFournisseur', 'damage_cause'
        ];
        $insert_fields = implode(", ", $fields) . ", date_sent";
        $insert_placeholders = ":" . implode(", :", $fields) . ", :date_sent";
        $insert = $pdo->prepare("INSERT INTO materiel_en_reparation ($insert_fields) VALUES ($insert_placeholders)");
        $params = [];
        foreach ($fields as $f) {
            $params[$f] = $mat[$f] ?? null;
        }
        $params['date_sent'] = date('Y-m-d H:i:s');
        $insert->execute($params);
        // Remove from materiel
        $del = $pdo->prepare("DELETE FROM materiel WHERE NumSerie = ?");
        $del->execute([$numserie]);
    $ste = urlencode($mat['STE'] ?? 'prod');
    header('Location: index.php?tab=maintenance&ste=' . $ste . '&sent=1');
        exit();
    } else {
        $error = 'Matériel introuvable.';
    }
}
if (!$numserie) {
    die('Numéro de série manquant.');
}
$stmt = $pdo->prepare("SELECT m.*, u.NomPrenom, u.Email, u.Tel, ma.Marque, t.Libelle as TypeLibelle FROM materiel m LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte LEFT JOIN marque ma ON m.CodeMarque = ma.Code LEFT JOIN type t ON m.CodeType = t.CodeType WHERE m.NumSerie = ?");
$stmt->execute([$numserie]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$mat) {
    die('Matériel introuvable.');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de réparation - <?= htmlspecialchars($mat['NumSerie']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff; margin: 0; }
        .fiche-container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 18px; box-shadow: 0 2px 18px rgba(0,0,0,0.09); padding: 0 0 40px 0; }
        .fiche-header, .fiche-section-title { background: #FFD600; color: #222; text-align: center; font-weight: bold; }
        .fiche-header { font-size: 32px; padding: 24px 0 18px 0; border-radius: 18px 18px 0 0; letter-spacing: 1px; margin-bottom: 24px; }
        .fiche-section-title { font-size: 20px; padding: 10px 0; margin-top: 28px; margin-bottom: 0; border-radius: 8px 8px 0 0; }
        .fiche-table { width: 100%; border-collapse: collapse; margin: 0 0 18px 0; }
        .fiche-table td { padding: 12px 16px; font-size: 17px; }
        .fiche-table input[type='text'], .fiche-table input[type='date'] { border: none; border-bottom: 1.5px solid #aaa; background: transparent; width: 90%; font-size: 16px; padding: 4px 0; }
        .fiche-table input[type='checkbox'] { transform: scale(1.2); margin-left: 8px; }
        .fiche-table label { font-weight: normal; }
        .fiche-table .label { font-weight: bold; color: #444; }
        .fiche-table .checkbox-group { display: flex; align-items: center; gap: 16px; }
        .fiche-textarea { width: 99%; min-height: 100px; border: 1.5px solid #ccc; border-radius: 6px; margin: 14px 0 18px 0; font-size: 16px; padding: 10px; resize: vertical; }
        .fiche-table-travaux { width: 100%; border-collapse: collapse; margin-top: 0; }
        .fiche-table-travaux th, .fiche-table-travaux td { border: 1px solid #bbb; padding: 10px 12px; font-size: 16px; text-align: left; }
        .fiche-table-travaux th { background: #FFD600; color: #222; font-weight: bold; text-align: center; }
        .fiche-table-travaux td { min-width: 80px; height: 32px; }
        .fiche-table-travaux .mo { width: 70px; text-align: center; }
        .fiche-table-travaux .date { width: 110px; text-align: center; }
        .fiche-table-travaux .desc { width: 340px; }
        .fiche-table-travaux .ouvrier { width: 140px; text-align: center; }
        .fiche-total { text-align: right; font-weight: bold; padding: 18px 0 0 0; font-size: 18px; }
        .print-btn { position: absolute; right: 60px; top: 32px; background: #FFD600; color: #222; border: none; padding: 12px 28px; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.07); transition: background 0.2s; }
        .print-btn:hover { background: #ffe066; }
        @media print {
            body, .fiche-header, .fiche-section-title, th, .fiche-table-travaux th {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body { background: #fff; }
            .fiche-container { box-shadow: none; }
            .no-print, .print-btn { display: none !important; }
        }
    </style>
</head>
<body>
<!-- Redesigned action bar for PDF and Send buttons at the bottom -->
<style>
.fiche-action-bar {
    display: flex;
    justify-content: center;
    gap: 32px;
    margin: 48px auto 0 auto;
    padding: 28px 0 40px 0;
    background: #f8f8f8;
    border-radius: 18px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    max-width: 900px;
    position: relative;
}
.fiche-action-bar .fiche-btn {
    font-size: 1.25rem;
    font-weight: bold;
    padding: 18px 38px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.18s, color 0.18s, box-shadow 0.18s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
}
.fiche-action-bar .fiche-btn.pdf {
    background: #FFD600;
    color: #222;
}
.fiche-action-bar .fiche-btn.pdf:hover {
    background: #ffe066;
    color: #111;
}
.fiche-action-bar .fiche-btn.send {
    background: #4CAF50;
    color: #fff;
}
.fiche-action-bar .fiche-btn.send:hover {
    background: #6fdc7a;
    color: #fff;
}
</style>
<div class="fiche-container">
    <div class="fiche-header">FICHE DE RÉPARATION</div>
    <table class="fiche-table">
        <tr>
            <td class="label">Nom du Client :</td>
            <td><?= htmlspecialchars($mat['NomPrenom'] ?? '') ?></td>
            <td class="label">Email :</td>
            <td><?= htmlspecialchars($mat['Email'] ?? 'N/A') ?></td>
        </tr>
        <tr>
            <td class="label">Matériel :</td>
            <td><?= htmlspecialchars($mat['TypeLibelle'] ?? '') ?></td>
            <td class="label">Modèle :</td>
            <td><?= htmlspecialchars($mat['Model'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="label">Entrée le :</td>
            <td><?= htmlspecialchars($mat['Dateentree'] ?? '') ?></td>
            <td class="label">Tél. :</td>
            <td><?= htmlspecialchars($mat['Tel'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="label">Promis le :</td>
            <td><input type="text" style="width: 80%;" placeholder="__/__/____"></td>
            <td class="label">Sous garantie :</td>
            <td class="checkbox-group">
                <label><input type="checkbox" name="garantie" value="oui"> OUI</label>
                <label><input type="checkbox" name="garantie" value="non"> NON</label>
            </td>
        </tr>
    </table>
    <div class="fiche-section-title">Travaux demandés par le client</div>
    <textarea class="fiche-textarea" placeholder="Travaux à effectuer..."></textarea>
    <div class="fiche-section-title">Travaux réalisés</div>
    <table class="fiche-table-travaux">
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Ouvrier</th>
                <th class="mo">MO</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < 6; $i++): ?>
            <tr>
                <td class="date"></td>
                <td class="desc"></td>
                <td class="ouvrier"></td>
                <td class="mo"></td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    <div class="fiche-total">TOTAL TEMPS ___________________________</div>
</div>
<div class="fiche-action-bar no-print">
    <button type="button" class="fiche-btn pdf" onclick="window.print()" title="Cliquez pour exporter cette fiche en PDF via l'impression du navigateur">Importer PDF</button>
    <form method="POST" style="display:inline; margin:0;">
        <input type="hidden" name="numserie" value="<?= htmlspecialchars($mat['NumSerie']) ?>">
        <input type="hidden" name="ste" value="<?= htmlspecialchars($mat['STE'] ?? '') ?>">
        <button type="submit" name="send_to_repair" class="fiche-btn send"  onclick="return confirm('Envoyer ce matériel en réparation ? Il sera retiré de la liste principale.'); ">Envoyer en réparation</button>
    </form>
</div>
</body>
</html> 