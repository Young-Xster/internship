<?php
// php/tabs/inventaire.php

$is_inventory_running = isset($_GET['inventory_status']) && $_GET['inventory_status'] === 'running';
$state_filter = $_GET['state'] ?? 'all';
$ste_filter = $_GET['ste'] ?? 'prod';

// Department selection form
echo '<form method="get" action="index.php" class="department-selector-form">';
echo '<input type="hidden" name="tab" value="inventaire">';
echo '<label for="ste">Département:</label>';
echo '<select name="ste" id="ste" onchange="this.form.submit()">';
echo '<option value="prod" ' . ($ste_filter === 'prod' ? 'selected' : '') . '>Production</option>';
echo '<option value="it" ' . ($ste_filter === 'it' ? 'selected' : '') . '>IT</option>';
echo '</select>';
echo '</form>';

if (!$is_inventory_running) {
    // Show "Start Inventory" button
    echo '<h2>Lancer un nouvel inventaire</h2>';
    echo '<p>Cliquez sur le bouton ci-dessous pour commencer l\'inventaire pour le département sélectionné.</p>';
    echo '<form method="get" action="index.php">';
    echo '<input type="hidden" name="tab" value="inventaire">';
    echo '<input type="hidden" name="ste" value="' . htmlspecialchars($ste_filter) . '">';
    echo '<input type="hidden" name="inventory_status" value="running">';
    echo '<button type="submit" class="btn-start-inventory">Début inventaire</button>';
    echo '</form>';
} else {
    // Display the inventory form
    echo '<h2>Inventaire en cours pour ' . strtoupper(htmlspecialchars($ste_filter)) . '</h2>';
    
    // State filters for inventory
    $states = ['all' => 'Tous', 'en-service' => 'En service', 'en-stock' => 'En stock', 'endommage' => 'Endommagé', 'casse' => 'Cassé'];
    echo '<div class="state-filters">';
    foreach ($states as $key => $label) {
        $activeClass = ($state_filter === $key) ? 'active' : '';
        echo '<a href="index.php?tab=inventaire&ste=' . $ste_filter . '&inventory_status=running&state=' . $key . '" class="' . $activeClass . '">' . $label . '</a>';
    }
    echo '</div>';

    // Fetch materials for the current STE and state
    $query = "SELECT m.*, u.NomPrenom as Utilisateur, t.Libelle as Type, mar.Marque as Marque 
              FROM materiel m
              LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte
              LEFT JOIN type t ON m.ID_Type = t.CodeType
              LEFT JOIN marque mar ON m.ID_Marque = mar.Code
              WHERE m.STE = ? AND (m.inventair = 0 OR m.inventair IS NULL)";
    
    $params = [$ste_filter];
    if ($state_filter !== 'all') {
        $stock_value = array_search($state_filter, $stockMap);
        if ($stock_value !== false) {
            $query .= " AND m.stock = ?";
            $params[] = $stock_value;
        }
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $materiels = $stmt->fetchAll();

    if (count($materiels) > 0) {
        echo '<form action="index.php?tab=inventaire&ste=' . $ste_filter . '" method="post">';
        echo '<input type="hidden" name="action" value="fin_inventaire">';
        echo '<input type="hidden" name="ste" value="' . htmlspecialchars($ste_filter) . '">';
        echo '<input type="hidden" name="state" value="' . htmlspecialchars($state_filter) . '">';
        echo '<table id="inventaire-table" class="table-style">';
        echo '<thead><tr><th><input type="checkbox" id="select-all"></th><th>Numéro de Série</th><th>Type</th><th>Marque</th><th>Modèle</th><th>Utilisateur</th><th>État</th></tr></thead>';
        echo '<tbody>';
        foreach ($materiels as $materiel) {
            $stock_status_class = htmlspecialchars($stockMap[$materiel['stock']] ?? '');
            $stock_status_label = htmlspecialchars($stockLabelMap[$materiel['stock']] ?? 'N/A');
            echo '<tr>';
            echo '<td><input type="checkbox" name="present[]" value="' . htmlspecialchars($materiel['NumSerie']) . '" checked></td>';
            echo '<td>' . htmlspecialchars($materiel['NumSerie']) . '</td>';
            echo '<td>' . htmlspecialchars($materiel['Type']) . '</td>';
            echo '<td>' . htmlspecialchars($materiel['Marque']) . '</td>';
            echo '<td>' . htmlspecialchars($materiel['Model']) . '</td>';
            echo '<td>' . htmlspecialchars($materiel['Utilisateur'] ?? 'Non attribué') . '</td>';
            echo '<td><span class="status ' . $stock_status_class . '">' . $stock_status_label . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<button type="submit" class="btn-submit">Finaliser l\'inventaire</button>';
        echo '</form>';
    } else {
        echo '<p>Aucun matériel à inventorier pour les filtres sélectionnés.</p>';
    }

    // Section for materials already in inventory (the "missing" list)
    $inv_query = "SELECT m.*, u.NomPrenom as Utilisateur, t.Libelle as Type, mar.Marque as Marque 
                  FROM materiel m
                  LEFT JOIN utilisateur u ON m.CodeUtilisateur = u.Compte
                  LEFT JOIN type t ON m.ID_Type = t.CodeType
                  LEFT JOIN marque mar ON m.ID_Marque = mar.Code
                  WHERE m.STE = ? AND m.inventair = 1";
    $inv_stmt = $pdo->prepare($inv_query);
    $inv_stmt->execute([$ste_filter]);
    $inventoried_materiels = $inv_stmt->fetchAll();

    if (count($inventoried_materiels) > 0) {
        echo '<h3>Matériel manquant (déjà dans l\'inventaire)</h3>';
        echo '<table id="inventoried-table" class="table-style">';
        echo '<thead><tr><th>Numéro de Série</th><th>Type</th><th>Marque</th><th>Modèle</th><th>Utilisateur</th><th>Date Inventaire</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        foreach ($inventoried_materiels as $materiel) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($materiel['NumSerie']) . '</td>';
            echo '<td>' . htmlspecialchars($materiel['Type']) . '</td>';
            echo '<td>' . htmlspecialchars($materiel['Marque']) . '</td>';
            echo '<td>' . htmlspecialchars($materiel['Model']) . '</td>';
            echo '<td>' . htmlspecialchars($materiel['Utilisateur'] ?? 'Non attribué') . '</td>';
            echo '<td>' . htmlspecialchars($materiel['dateinvent']) . '</td>';
            echo '<td>
                    <form action="index.php?tab=inventaire&ste=' . $ste_filter . '" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="recuperer_inventaire">
                        <input type="hidden" name="NumSerie" value="' . htmlspecialchars($materiel['NumSerie']) . '">
                        <input type="hidden" name="STE" value="' . htmlspecialchars($ste_filter) . '">
                        <button type="submit" class="btn-recover">Récupérer</button>
                    </form>
                  </td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('#inventaire-table tbody input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    }
});
</script>
