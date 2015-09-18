<?php
/**
 * набивка писем о окончании подписки и смена статуса подписки на неактивную
 */

 if(isset($_GET['t'])) define('D', true);
 
define('MODX_API_MODE', true);
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';
$modx->getService('error', 'error.modError');
$modx->getRequest();
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');
$modx->error->message = null;

$payandsee = $modx->getService('payandsee');

$time=time();

$now = date("Y-m-d H:i:s");
$week = date("Y-m-d H:i:s", $time+604800);
$sixdays = date("Y-m-d H:i:s", $time+518400);

$sixdays = 0;

$active = 1;
// выбираем активные подписки с просроченной датой окончания
$q = $modx->newQuery('PaySeeList', array('active' => $active));
$q->leftJoin('PaySeeResourceSettings','PaySeeResourceSettings','PaySeeResourceSettings.resource_id = PaySeeList.resource_id');
$q->where(array(
	'PaySeeList.stopdate:<=' => $week,
	'PaySeeList.stopdate:>' => $sixdays,
));
$q->select($modx->getSelectColumns('PaySeeList','PaySeeList'));
$q->select($modx->getSelectColumns('PaySeeResourceSettings','PaySeeResourceSettings'));

$data = $modx->getIterator('PaySeeList', $q);
$modx->lexicon->load('payandsee:default');

foreach ($data as $d) {
	$pas = $d->toArray();
	$subject = '';
	if ($chunk = $modx->newObject('modChunk', array('snippet' => $modx->lexicon('pas_subject_notify')))){
		$chunk->setCacheable(false);
		$subject = $payandsee->processTags($chunk->process($pas));
	}
	$body = 'no chunk set';
	if ($chunk = $modx->getObject('modChunk', $modx->getOption('payandsee_chunk_notify', null, 66))) {
		$chunk->setCacheable(false);
		$body = $payandsee->processTags($chunk->process($pas));
	}
	if (!empty($subject)) {
		$user = $modx->getObject('modUser', $pas['user_id']);
		$profile=$user->getOne('Profile');
		
		// письмо пользователю
		$payandsee->addQueue($pas['user_id'], $subject, $body, $profile->get('email'));
		
		// смена статуса подписки на неактивную
		/*
		if ($data_ = $modx->getObject('PaySeeList', array(
			'resource_id' => $pas['resource_id'],
			'user_id' => $pas['user_id'],
		))) {
			$data_->fromArray(array('active' => 0));
			$data_->save();
		}
		*/
		
		// письмо менеджеру
		$emails = array_map('trim', explode(',', $modx->getOption('payandsee_email_manager', null, $modx->getOption('emailsender'))));
		foreach ($emails as $email) {
			if (preg_match('/^[^@а-яА-Я]+@[^@а-яА-Я]+(?<!\.)\.[^\.а-яА-Я]{2,}$/m', $email)) {
				$payandsee->addQueue('', $subject, $body, $email);
			}
		}
		
	}

}