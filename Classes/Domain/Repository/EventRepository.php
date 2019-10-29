<?php

/**
 * Event repository.
 */
declare(strict_types=1);

namespace HDNET\Calendarize\Domain\Repository;

use HDNET\Calendarize\Domain\Model\Event;
use HDNET\Calendarize\Domain\Model\Index;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * Event repository.
 */
class EventRepository extends AbstractRepository
{

    public function findEventByImportId($importId)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false)->setIgnoreEnableFields(true);
        $query->matching($query->equals('import_id', $importId))
            ->setLimit(1);
        $result = $query->execute();
        return $result->getFirst();
    }

    /**
     * Get the IDs of the given search term.
     *
     * @param string $searchTerm
     *
     * @return array
     */
    public function getIdsBySearchTerm($searchTerm)
    {
        $query = $this->createQuery();
        $constraint = [];
        $constraint[] = $query->like('title', '%' . $searchTerm . '%');
        $constraint[] = $query->like('description', '%' . $searchTerm . '%');
        $query->matching($query->logicalOr($constraint));
        $rows = $query->execute(true);

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['uid'];
        }

        return $ids;
    }

    /**
     * Return the current tablename.
     *
     * @return string
     */
    public function getTableName()
    {
        $query = $this->createQuery();
        if ($query instanceof Query) {
            $source = $query->getSource();
            if (\method_exists($source, 'getSelectorName')) {
                return $source->getSelectorName();
            }
        }
    }

    /**
     * Get the right Index ID by the event ID.
     *
     * @param int $uid
     *
     * @return Index|null
     */
    protected function findNextIndex(int $uid)
    {
        /** @var Event $event */
        $event = $this->findByUid($uid);

        if (!\is_object($event)) {
            return;
        }

        $objectManager = new ObjectManager();
        /** @var IndexRepository $indexRepository */
        $indexRepository = $objectManager->get(IndexRepository::class);

        try {
            $result = $indexRepository->findByEventTraversing($event, true, false, 1, QueryInterface::ORDER_ASCENDING);
            if (empty($result)) {
                $result = $indexRepository->findByEventTraversing($event, false, true, 1, QueryInterface::ORDER_DESCENDING);
            }
        } catch (\Exception $ex) {
            return;
        }

        if (empty($result)) {
            return;
        }

        /** @var Index $index */
        $index = $result[0];

        return $index;
    }
}
