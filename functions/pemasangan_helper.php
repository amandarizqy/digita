<?php
// functions/pemasangan_helper.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function p_role() {
    return $_SESSION['KodeHak'] ?? $_SESSION['Role'] ?? 'GUEST';
}

function p_akses_order() {
    $role = p_role();
    return in_array($role, ['SA.KP', 'TL.UP', 'TL.AP', 'SF.UP'], true);
}

function p_akses_mutasi() {
    $role = p_role();
    return in_array($role, ['SA.KP', 'SF.UP'], true);
}

function p_akses_monitoring() {
    $role = p_role();
    $role_monitoring = ['SA.KP', 'MB.UI', 'AM.UI', 'AM.AP', 'ML.UP', 'TL.AP', 'TL.UP', 'SF.UI', 'SF.AP', 'SF.UP', 'SF.VD'];
    return in_array($role, $role_monitoring, true);
}