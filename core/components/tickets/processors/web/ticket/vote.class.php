<?php
class TicketVoteProcessor extends modObjectCreateProcessor {
	/** @var TicketVote $object */
	public $object;
	/* @var Ticket|modResource $ticket */
	private $ticket;
	public $objectType = 'TicketVote';
	public $classKey = 'TicketVote';
	public $languageTopics = array('tickets:default');
	public $permission = 'ticket_vote';


	/** {@inheritDoc} */
	public function beforeSet() {
		$id = $this->getProperty('id');

		if (!$this->modx->user->isAuthenticated($this->modx->context->key)) {
			return $this->modx->lexicon('permission_denied');
		}
		elseif (!$this->ticket = $this->modx->getObject('modResource', $id)) {
			return $this->modx->lexicon('ticket_err_ticket');
		}
		elseif ($this->ticket->createdby == $this->modx->user->id) {
			return $this->modx->lexicon('ticket_err_vote_own');
		}
		/** @var TicketVote $vote */
		elseif ($this->modx->getCount($this->classKey, array('id' => $id, 'createdby' => $this->modx->user->id, 'class' => 'Ticket'))) {
			return $this->modx->lexicon('ticket_err_vote_already');
		}

		return true;
	}


	/** {@inheritDoc} */
	public function beforeSave() {
		$this->modx->getRequest();
		$ip = $this->modx->request->getClientIp();

		$value = $this->getProperty('value');
		if ($value > 0) {
			$value = 1;
		}
		elseif ($value < 0) {
			$value = -1;
		}
		else {
			$value = 0;
		}

		$this->object->set('id', $this->ticket->id);
		$this->object->set('owner', $this->ticket->createdby);
		$this->object->set('class', 'Ticket');
		$this->object->set('value', $value);
		$this->object->set('ip', $ip['ip']);
		$this->object->set('createdon', date('Y-m-d H:i:s'));
		$this->object->set('createdby', $this->modx->user->id);

		return true;
	}


	/** {@inheritDoc} */
	public function cleanup() {
		if ($this->ticket instanceof Ticket) {
			$rating = $this->ticket->updateRating();
		}
		else {
			$rating = array('rating' => 0, 'rating_plus' => 0, 'rating_minus' => 0);

			$q = $this->modx->newQuery('TicketVote', array('id' => $this->ticket->id, 'class' => 'Ticket'));
			$q->innerJoin('modUser', 'modUser', '`modUser`.`id` = `TicketVote`.`createdby`');
			$q->select('value');
			$tstart = microtime(true);
			if ($q->prepare() && $q->stmt->execute()) {
				$this->modx->startTime += microtime(true) - $tstart;
				$this->modx->executedQueries ++;
				$rows = $q->stmt->fetchAll(PDO::FETCH_COLUMN);
				foreach ($rows as $value) {
					$rating['rating'] += $value;
					if ($value > 0) {
						$rating['rating_plus'] += $value;
					}
					elseif ($value < 0) {
						$rating['rating_minus'] += $value;
					}
				}
				$this->ticket->setProperties($rating, 'tickets', true);
				$this->ticket->save();
				$this->clearCache();
			}
		}

		return $this->success('', $rating);
	}


	/**
	 * Clear ticket cache
	 */
	public function clearCache() {
		$context = $this->ticket->get('context_key');
		$this->ticket->_contextKey = $context;

		/** @var xPDOFileCache $cache */
		$cache = $this->modx->cacheManager->getCacheProvider($this->modx->getOption('cache_resource_key', null, 'resource'));
		$key = $this->ticket->getCacheKey();
		$cache->delete($key, array('deleteTop' => false));
		$cache->delete($key);
	}

}

return 'TicketVoteProcessor';