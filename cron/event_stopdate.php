<?php
/**
 * набивка писем о окончании подписки и смена статуса подписки на неактивную
 */
 
define('MODX_API_MODE', true);
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';
$modx->getService('error', 'error.modError');
$modx->getRequest();
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');
$modx->error->message = null;

$payandsee = $modx->getService('payandsee');

$now = date("Y-m-d H:i:s");

$active = 1;
// выбираем активные подписки с просроченной датой окончания
$q = $modx->newQuery('PaySeeList', array('active' => $active));
$q->leftJoin('PaySeeResourceSettings','PaySeeResourceSettings','PaySeeResourceSettings.resource_id = PaySeeList.resource_id');
$q->where(array(
	'PaySeeList.stopdate:<=' => $now,
));
$q->select($modx->getSelectColumns('PaySeeList','PaySeeList'));
$q->select($modx->getSelectColumns('PaySeeResourceSettings','PaySeeResourceSettings'));

$data = $modx->getIterator('PaySeeList', $q);
$modx->lexicon->load('payandsee:default');

foreach ($data as $d) {
	$pls = $d->toArray();
	$subject = '';
	if ($chunk = $modx->newObject('modChunk', array('snippet' => $modx->lexicon('pas_subject_stopdate')))){
		$chunk->setCacheable(false);
		$subject = $payandsee->processTags($chunk->process($pls));
	}
	$body = 'no chunk set';
	if ($chunk = $modx->getObject('modChunk', $modx->getOption('payandsee_chunk_stopdate', null, 66))) {
		$chunk->setCacheable(false);
		$body = $payandsee->processTags($chunk->process($pls));
	}
	if (!empty($subject)) {
		// письмо пользователю
		$payandsee->addQueue($pls['user_id'], $subject, $body, '');
		// смена статуса подписки на неактивную
		if ($data_ = $modx->getObject('PaySeeList', array(
			'resource_id' => $pls['resource_id'],
			'user_id' => $pls['user_id'],
		))) {
			$data_->fromArray(array('active' => 0));
			$data_->save();
		}
		// письмо менеджеру
		$emails = array_map('trim', explode(',', $modx->getOption('payandsee_email_manager', null, $modx->getOption('emailsender'))));
		foreach ($emails as $email) {
			if (preg_match('/^[^@а-яА-Я]+@[^@а-яА-Я]+(?<!\.)\.[^\.а-яА-Я]{2,}$/m', $email)) {
				$payandsee->addQueue('', $subject, $body, $email);
			}
		}
	}

}