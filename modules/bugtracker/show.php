<?php
$LSCurFile = __FILE__;

include_once('modules/bugtracker/class_bugtracker.php');
$bugtracker = new Bugtracker();

$types = array();
$types['1'] = t('Feature Wunsch');
$types['2'] = t('Schreibfehler');
$types['3'] = t('Kleiner Fehler');
$types['4'] = t('Schwerer Fehler');
$types['5'] = t('Absturz');

$colors = array();
$colors[0] = '#bc851b';
$colors[1] = '#dc5656';
$colors[2] = '#e19501';
$colors[3] = '#019ae1';
$colors[4] = '#67a900';
$colors[5] = '#aaaaaa';
$colors[6] = '#999999';
$colors[7] = '#bc851b';

if ($_POST['action']) foreach ($_POST['action'] as $key => $val) {

  if ($auth['login']) {
    // Change state
    if ($_GET['state'] != '' and $_GET['state'] < 2) $bugtracker->SetBugState($key, $_GET['state']);
  }
  if ($auth['type'] >= 2) {
    // Change state
    if ($_GET['state'] != '' and $_GET['state'] >= 2) $bugtracker->SetBugState($key, $_GET['state']);

    // Assign to new user
    if ($_GET['userid'] != '') $bugtracker->AssignBugToUser($key, $_GET['userid']);
  }
}

if ($_GET['action'] == 'delete' and $auth['type'] >= 2) {
  if ($_GET['bugid'] != '') {
    include_once('inc/classes/class_masterdelete.php');
    $md = new masterdelete();
    $md->Delete('bugtracker', 'bugid', $_GET['bugid']);
  } else {
    include_once('inc/classes/class_masterdelete.php');
    $md = new masterdelete();
    $md->MultiDelete('bugtracker', 'bugid');
  }
}

function FetchState($state) {
  global $bugtracker;
  return $bugtracker->stati[$state];
}

function FetchType($type) {
  global $types;
  return $types[$type];
}

if (!$_GET['bugid'] or $_GET['action'] == 'delete') {
  $dsp->NewContent(t('Bugtracker'), t('Hier können Sie Fehler melden, die bei der Verwendung dieses Systems auftreten, sowie Feature Wünsche äußern. Können die Admins dieser Webseite sie nicht selbst beheben, haben diese die Möglichkeit sie an das Lansuite-Team weiterzureichen.'));

  include_once('modules/mastersearch2/class_mastersearch2.php');
  $ms2 = new mastersearch2('bugtracker');

  $ms2->query['from'] = "{$config["tables"]["bugtracker"]} AS b
    LEFT JOIN {$config["tables"]["user"]} AS r ON b.reporter = r.userid
    LEFT JOIN {$config["tables"]["user"]} AS a ON b.agent = a.userid
    LEFT JOIN {$config["tables"]["comments"]} AS c ON (c.relatedto_id = b.bugid AND c.relatedto_item = 'BugEintrag')
    ";
  $ms2->query['default_order_by'] = 'state ASC, date DESC';
  $ms2->config['EntriesPerPage'] = 50;

  $ms2->AddBGColor('state', $colors);

  $ms2->AddTextSearchField(t('Überschrift'), array('b.caption' => 'like'));
  $ms2->AddTextSearchField(t('Text'), array('b.text' => 'fulltext', 'c.text' => 'fulltext'));

  $list = array('' => 'Alle');
  $row = $db->qry('SELECT b.reporter, u.username FROM %prefix%bugtracker AS b LEFT JOIN %prefix%user AS u ON b.reporter = u.userid WHERE b.reporter > 0 ORDER BY u.username');
  while($res = $db->fetch_array($row)) $list[$res['reporter']] = $res['username'];
  $db->free_result($row);
  $ms2->AddTextSearchDropDown('Reporter', 'b.reporter', $list);

  $list = array('' => 'Alle');
  $row = $db->qry('SELECT b.agent, u.username FROM %prefix%bugtracker AS b LEFT JOIN %prefix%user AS u ON b.agent = u.userid WHERE b.agent > 0 ORDER BY u.username');
  while($res = $db->fetch_array($row)) $list[$res['agent']] = $res['username'];
  $db->free_result($row);
  $ms2->AddTextSearchDropDown('Bearbeiter', 'b.agent', $list);

  $list = array('' => 'Alle');
  $row = $db->qry('SELECT c.creatorid, u.username FROM %prefix%comments AS c LEFT JOIN %prefix%user AS u ON c.creatorid = u.userid WHERE c.creatorid > 0 AND c.relatedto_item = \'BugEintrag\' ORDER BY u.username');
  while($res = $db->fetch_array($row)) $list[$res['creatorid']] = $res['username'];
  $db->free_result($row);
  $ms2->AddTextSearchDropDown('Kommentator', 'c.creatorid', $list);

  $list = array_merge(array('' => 'Alle'), $bugtracker->stati);
  $ms2->AddTextSearchDropDown('Status', 'b.state', $list);

#  $list = array_merge(array('' => 'Alle'), $types);
#  $ms2->AddTextSearchDropDown('Fehlertyp', 'b.type', $list);

  $ms2->AddResultField(t('Titel'), 'b.caption');
  $ms2->AddSelect('r.userid');
  $ms2->AddResultField(t('Typ'), 'b.type', 'FetchType');
  $ms2->AddResultField(t('Prio.'), 'b.priority');
  $ms2->AddResultField(t('Status'), 'b.state', 'FetchState');
  $ms2->AddResultField(t('Reporter'), 'r.username AS reporter', 'UserNameAndIcon');
  $ms2->AddResultField(t('Bearbeiter'), 'a.username AS agent');
  $ms2->AddResultField(t('Antw.'), 'COUNT(c.relatedto_id) AS comments');
  $ms2->AddResultField(t('Datum'), 'UNIX_TIMESTAMP(b.date) AS date', 'MS2GetDate');

  $ms2->AddIconField('details', 'index.php?mod=bugtracker&bugid=', t('Details'));
  if ($auth['type'] >= 2) $ms2->AddIconField('edit', 'index.php?mod=bugtracker&action=add&bugid=', t('Editieren'));
  if ($auth['type'] >= 3) $ms2->AddIconField('delete', 'index.php?mod=bugtracker&action=delete&bugid=', t('Löschen'));

  if ($auth['type'] >= 2) {
    foreach($bugtracker->stati as $key => $val) $ms2->AddMultiSelectAction(t('Status') .' -> '. $val, 'index.php?mod=bugtracker&state='. $key);

    $ms2->AddMultiSelectAction(t('Bearbeiter löschen'), 'index.php?mod=bugtracker&userid=0');
    $res = $db->query("SELECT userid, username FROM {$config['tables']['user']} WHERE type >= 2");
    while ($row = $db->fetch_array($res)) $ms2->AddMultiSelectAction(t('Bearbeiter') .' -> '. $row['username'], 'index.php?mod=bugtracker&userid='. $row['userid']);
    $db->free_result($res);

    $ms2->AddMultiSelectAction(t('Löschen'), 'index.php?mod=bugtracker&action=delete');

  } elseif ($auth['login']) {
    $ms2->AddMultiSelectAction(t('Status') .' -> '. $bugtracker->stati[1], 'index.php?mod=bugtracker&state=1');
    $ms2->AddMultiSelectAction(t('Status') .' -> '. $bugtracker->stati[2], 'index.php?mod=bugtracker&state=2');
    $ms2->AddMultiSelectAction(t('Status') .' -> '. $bugtracker->stati[7], 'index.php?mod=bugtracker&state=7');
  }

  $ms2->PrintSearch('index.php?mod=bugtracker', 'b.bugid');

// Details page
} else {
  $row = $db->query_first("SELECT b.*, r.username AS reporter_name, a.username AS agent_name FROM {$config['tables']['bugtracker']} AS b
    LEFT JOIN {$config["tables"]["user"]} AS r ON b.reporter = r.userid
    LEFT JOIN {$config["tables"]["user"]} AS a ON b.agent = a.userid
    WHERE bugid=". (int)$_GET['bugid']
    );

  $dsp->NewContent($row['caption'], $types[$row['type']] .', '. t('Priorität') .': '. $row['priority']);

	$dsp->AddDoubleRow(t('Herkunft'), '<a href="'. $row['url'] .'" target="_blank">'. $row['url'] .'</a> Version('. $row['version'] .')');
	$dsp->AddDoubleRow(t('Reporter'), $row['reporter_name'] .' '. $dsp->FetchUserIcon($row['reporter']));
	$dsp->AddDoubleRow(t('Betrifft Modul'), $row['module']);
	$dsp->AddDoubleRow(t('Meldezeitpunkt'), $row['date']);

	$dsp->AddDoubleRow(t('Status'), $bugtracker->stati[$row['state']]);
	if ($row['agent']) $dsp->AddDoubleRow(t('Bearbeiter'), $row['agent_name'] .' '. $dsp->FetchUserIcon($row['agent']));
	else $dsp->AddDoubleRow(t('Bearbeiter'), t('Noch nicht zugeordnet'));

	$dsp->AddDoubleRow(t('Text'), $func->text2html($row['text']));
  if ($row['file']) $dsp->AddDoubleRow(t('Anhang'), $dsp->FetchAttachmentRow($row['file']));
	$dsp->AddDoubleRow('', $dsp->FetchSpanButton(t('Editieren'), 'index.php?mod=bugtracker&action=add&bugid='.$row['bugid']) . $dsp->FetchSpanButton(t('Zurück zur Übersicht'), 'index.php?mod=bugtracker'));

  if ($auth['login']) {
    include_once('inc/classes/class_masterform.php');
    $mf = new masterform();
    $mf->ManualUpdate = 1;
    if ($auth['type'] >= 2) $mf->AddField(t('Status'), 'state', IS_SELECTION, $bugtracker->stati);
    elseif ($row['state'] == 0) $mf->AddField(t('Status'), 'state', IS_SELECTION, array('1' => $bugtracker->stati['1']));
    elseif ($row['state'] == 4) $mf->AddField(t('Status'), 'state', IS_SELECTION, array('7' => $bugtracker->stati['7']));
    elseif ($row['state'] == 3) $mf->AddField(t('Status'), 'state', IS_SELECTION, array('2' => $bugtracker->stati['2']));

    if ($mf->SendForm('', 'bugtracker', 'bugid', $_GET['bugid'])) {
      $bugtracker->SetBugState($_GET['bugid'], $_POST['state']);
      $func->confirmation(t('Geändert'), $mf->LinkBack);
    }
  }

	include('inc/classes/class_mastercomment.php');
	new Mastercomment('BugEintrag', $_GET['bugid']);
	
	$dsp->AddFieldsetStart('Log');
  include_once('modules/mastersearch2/class_mastersearch2.php');
  $ms2 = new mastersearch2('bugtracker');

  $ms2->query['from'] = "{$config["tables"]["log"]} AS l LEFT JOIN {$config["tables"]["user"]} AS u ON l.userid = u.userid";
  $ms2->query['where'] = "(sort_tag = 'bugtracker' AND target_id = ". (int)$_GET['bugid'] .')';

  $ms2->AddResultField(t('Nachricht'), 'l.description');
  $ms2->AddSelect('u.userid');
  $ms2->AddResultField(t('Auslöser'), 'u.username', 'UserNameAndIcon');
  $ms2->AddResultField(t('Datum'), 'l.date', 'MS2GetDate');
  $ms2->PrintSearch('index.php?mod=bugtracker', 'logid');
	$dsp->AddFieldsetEnd('Log');
}

$dsp->AddContent();
?>