<?php

/**
 * @param string $clanpw
 * @return bool|string
 * @throws Exception
 */
function CheckClanPWUsrMgr($clanpw)
{
    global $db, $auth;

    if (!$_POST['new_clan_select'] and $auth['type'] <= 1 and $auth['clanid'] != $_POST['clan']) {
        $clan = $db->qry_first("SELECT password FROM %prefix%clan WHERE clanid = %int%", $_POST['clan']);
        if ($clan['password'] and !\LanSuite\PasswordHash::verify($clanpw, $clan['password'])) {
            return t('Passwort falsch!');
        }
    }
    return false;
}
