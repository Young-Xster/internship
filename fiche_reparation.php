<?php
require_once 'php/config.php';
$numserie = $_GET['numserie'] ?? '';
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
<button class="print-btn" onclick="window.print()">🖨️ Imprimer / PDF</button>
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
</body>
</html> 