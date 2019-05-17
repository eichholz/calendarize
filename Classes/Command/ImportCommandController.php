<?php

/**
 * Import.
 */
declare(strict_types=1);

namespace HDNET\Calendarize\Command;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use Sabre\VObject;

/**
 * Import.
 */
class ImportCommandController extends AbstractCommandController
{
    /**
     * Import command.
     *
     * @param string $icsCalendarUri
     * @param int    $pid
     */
    public function importCommand($icsCalendarUri = null, $pid = null)
    {
        if (null === $icsCalendarUri || !\filter_var($icsCalendarUri, FILTER_VALIDATE_URL)) {
            $this->enqueueMessage('You have to enter a valid URL to the iCalendar ICS', 'Error', FlashMessage::ERROR);

            return;
        }
        if (!MathUtility::canBeInterpretedAsInteger($pid)) {
            $this->enqueueMessage('You have to enter a valid PID for the new created elements', 'Error', FlashMessage::ERROR);

            return;
        }

        // fetch external URI and write to file
        $this->enqueueMessage('Start to checkout the calendar: ' . $icsCalendarUri, 'Calendar', FlashMessage::INFO);
        $relativeIcalFile = 'typo3temp/ical.' . GeneralUtility::shortMD5($icsCalendarUri) . '.ical';
        $absoluteIcalFile = GeneralUtility::getFileAbsFileName($relativeIcalFile);
        $content = GeneralUtility::getUrl($icsCalendarUri);
        GeneralUtility::writeFile($absoluteIcalFile, $content);

        // get Events from file
        $icalEvents = $this->getIcalEvents($absoluteIcalFile);
        $this->enqueueMessage('Found ' . \count($icalEvents) . ' events in the given calendar', 'Items', FlashMessage::INFO);
        $events = $this->prepareEvents($icalEvents);

        $this->enqueueMessage('Found ' . \count($events) . ' events in ' . $icsCalendarUri, 'Items', FlashMessage::INFO);

        $signalSlotDispatcher = GeneralUtility::makeInstance(Dispatcher::class);

        $this->enqueueMessage('Send the ' . __CLASS__ . '::importCommand signal for each event.', 'Signal', FlashMessage::INFO);
        foreach ($events as $event) {
            $arguments = [
                'event' => $event,
                'commandController' => $this,
                'pid' => $pid,
                'handled' => false,
            ];
            $signalSlotDispatcher->dispatch(__CLASS__, 'importCommand', $arguments);
        }
    }

    /**
     * Prepare the events.
     *
     * @param VObject\Component\VEvent $icalEvents
     *
     * @return array
     */
    protected function prepareEvents(VObject\Component\VEvent $icalEvents)
    {
        $events = [];
        foreach ($icalEvents as $icalEvent) {
            $startDateTime = null;
            $endDateTime = null;
            $uid = (string)$icalEvent->UID;
            $summary = (string)$icalEvent->SUMMARY;
            $description = (string)$icalEvent->DESCRIPTION;
            $location = (string)$icalEvent->LOCATION;
            try {
                $startDateTime = new \DateTime((string)$icalEvent->DTSTART);
                $endDateTime = new \DateTime((string)$icalEvent->DTEND);
            } catch (\Exception $ex) {
                $this->enqueueMessage(
                    'Could not convert the date in the right format of "' . $summary . '"',
                    'Warning',
                    FlashMessage::WARNING
                );
                continue;
            }

            $events[] = [
                'uid' => $uid,
                'start' => $startDateTime,
                'end' => $endDateTime,
                'title' => $summary,
                'description' => $description,
                'location' => $location,
            ];
        }

        return $events;
    }

    /**
     * Get the events from the given ical file.
     *
     * @param string $absoluteIcalFile
     *
     * @return VObject\Property
     */
    protected function getIcalEvents($absoluteIcalFile)
    {
        $vcalendar = VObject\Reader::read(
            fopen($absoluteIcalFile, 'r')
        );
        return $vcalendar->VEVENT;
    }
}
