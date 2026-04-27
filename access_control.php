<?php
// Funções de controle de acesso baseado em perfil

function canViewAllData($role) {
    return in_array($role, ['Administrador', 'Suporte Técnico']);
}

function canViewSectorData($role) {
    return $role === 'Responsável de Setor';
}

function canViewOnlyOwnData($role) {
    return $role === 'Colaborador';
}

function canViewBudgets($role) {
    return in_array($role, ['Administrador', 'Suporte Técnico', 'Responsável de Setor', 'Setor de Compras']);
}

function applyAccessFilter($query, $params, $user, $table_alias = '') {
    $prefix = $table_alias ? $table_alias . '.' : '';
    
    if ($user['role'] == 'Responsável de Setor') {
        $query .= " AND {$prefix}unit_id = ? AND {$prefix}sector = ?";
        $params[] = $user['unit_id'];
        $params[] = $user['sector'];
    } elseif ($user['role'] == 'Colaborador') {
        // Colaborador vê apenas seus próprios dados
        if (strpos($query, 'requester_id') !== false) {
            $query .= " AND {$prefix}requester_id = ?";
            $params[] = $user['id'];
        } elseif (strpos($query, 'responsible_name') !== false) {
            $query .= " AND {$prefix}responsible_name = ?";
            $params[] = $user['name'];
        } elseif (strpos($query, 'name') !== false) {
            $query .= " AND {$prefix}name = ?";
            $params[] = $user['name'];
        }
    }
    
    return ['query' => $query, 'params' => $params];
}
?>
