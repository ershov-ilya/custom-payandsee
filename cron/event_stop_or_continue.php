<?php
/**
 * набивка писем о окончании подписки и смена статуса подписки на неактивную
 */
 
if(isset($_GET['t'])) define('D', true);
defined('D') or define('D', true);

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
$month = date("Y-m-d H:i:s",$time+2629800);

$active = 1;
// выбираем активные подписки с просроченной датой окончания
$modx->lexicon->load('payandsee:default');

if(D) {
	if(isset($_GET['id'])){
		print "<pre>".PHP_EOL;

        $q = $modx->newQuery('PaySeeList', array(
            //'active' => $active,
            'user_id' => 6,
        ));
        $q->leftJoin('PaySeeResourceSettings','PaySeeResourceSettings','PaySeeResourceSettings.resource_id = PaySeeList.resource_id');
        $q->where(array(
            'PaySeeList.stopdate:>' => 0,
        ));
        $q->select($modx->getSelectColumns('PaySeeList','PaySeeList'));
        $q->select($modx->getSelectColumns('PaySeeResourceSettings','PaySeeResourceSettings'));
        $data = $modx->getIterator('PaySeeList', $q);

        foreach ($data as $d) {
            $pas = $d->toArray();
            print_r($pas);
        }

		// Профиль покупателя
		$user_id= $_GET['id'];
		$user = $modx->getObject('modUser', $user_id);
		$profile=$user->getOne('Profile');
		print $profile->get('fullname').PHP_EOL;
		
		if (!$msCustomerProfile = $modx->getObject('msCustomerProfile', array('id' => $user_id))) {
			die('No customer profile');
		}else{
            $arrProfile=$msCustomerProfile->toArray();
			var_dump($arrProfile);
		}
		
		// Стоимость продления
        print "Стоимость продления".PHP_EOL;
        $cost=$pas['pas_price'];
        print $cost.PHP_EOL;
        print "Баланс".PHP_EOL;
        print $arrProfile['account'].PHP_EOL;

        // смена статуса подписки на продлённую
        $res=false;
        if ($objPAS = $modx->getObject('PaySeeList', array(
            'resource_id' => 16,
            'user_id' => 6,
        ))) {
            $objPAS->fromArray(array(
                'active' => 1,
                'stopdate' => $month,
            ));
            $res=$objPAS->save();
        }
        var_dump($res);

        if($res) {
            $res=false;
            // Вычитание баланса
            $msCustomerProfile->fromArray(array(
                'account' => $arrProfile['account'] - $cost
            ));
            $res=$msCustomerProfile->save();
        }

        die;
	}

}

$q = $modx->newQuery('PaySeeList', array('active' => $active));
$q->leftJoin('PaySeeResourceSettings','PaySeeResourceSettings','PaySeeResourceSettings.resource_id = PaySeeList.resource_id');
$q->where(array(
    'PaySeeList.stopdate:<=' => $now,
));
$q->select($modx->getSelectColumns('PaySeeList','PaySeeList'));
$q->select($modx->getSelectColumns('PaySeeResourceSettings','PaySeeResourceSettings'));
$data = $modx->getIterator('PaySeeList', $q);


foreach ($data as $d) {
	$pas = $d->toArray();
	$subject = '';
	if ($chunk = $modx->newObject('modChunk', array('snippet' => $modx->lexicon('pas_subject_stopdate')))){
		$chunk->setCacheable(false);
		$subject = $payandsee->processTags($chunk->process($pas));
	}
	$body = 'no chunk set';
	if ($chunk = $modx->getObject('modChunk', $modx->getOption('payandsee_chunk_stopdate', null, 66))) {
		$chunk->setCacheable(false);
		$body = $payandsee->processTags($chunk->process($pas));
	}
	if (!empty($subject)) {
		
		$user = $modx->getObject('modUser', $pas['user_id']);
		$profile=$user->getOne('Profile');
		
		$msprofile=$user->getOne('msProfile');
		
		if(D) {
			print_r($msprofile->toArray());
		}
		
		// письмо пользователю
		$payandsee->addQueue($pas['user_id'], $subject, $body, '');
		// смена статуса подписки на неактивную
		if ($data_ = $modx->getObject('PaySeeList', array(
			'resource_id' => $pas['resource_id'],
			'user_id' => $pas['user_id'],
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