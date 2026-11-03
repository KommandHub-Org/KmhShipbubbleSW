<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow;

use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentCreatedEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentFailedEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Installs every flow the plugin needs, so nothing has to be set up by hand
 * in Flow Builder (spec §10):
 *
 *  - "Shipbubble: create shipment when paid" — books the courier when the
 *    order is paid (active);
 *  - "Shipbubble: tracking email to customer" — sends the courier and tracking
 *    link once the label is booked (active);
 *  - "Shipbubble: booking failed alert" — emails the shop admin Shipbubble's
 *    reason when booking fails (active).
 *
 * Each flow has a fixed id and is created only when missing, so a flow the
 * merchant edited, switched off or deleted is never touched or brought back
 * by an update. Mail templates are created together with their flow.
 */
class FlowInstaller
{
    public const PAID_EVENT = 'state_enter.order_transaction.state.paid';

    public const CREATED_MAIL_TYPE = 'kmh_shipbubble_shipment_created';

    public const FAILED_MAIL_TYPE = 'kmh_shipbubble_shipment_failed';

    /**
     * @param EntityRepository<FlowCollection> $flowRepository
     * @param EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct(
        private readonly EntityRepository $flowRepository,
        private readonly EntityRepository $mailTemplateTypeRepository,
        private readonly EntityRepository $mailTemplateRepository,
    ) {
    }

    /**
     * Fixed ids of the flows this installer owns.
     *
     * @return list<string>
     */
    public static function flowIds(): array
    {
        return array_map(static fn (string $key): string => Uuid::fromStringToHex('kmh_shipbubble.flow.' . $key), ['paid', 'created', 'failed']);
    }

    public function install(Context $context): void
    {
        [$paidId, $createdId, $failedId] = self::flowIds();

        // Versions before fixed ids created the paid flow with a random id.
        if (!$this->flowExists($paidId, $context) && !$this->actionInUse($context)) {
            $this->createFlow(
                $paidId,
                'Shipbubble: create shipment when paid',
                self::PAID_EVENT,
                'Books the customer\'s Shipbubble courier as soon as the order is paid.',
                ['actionName' => CreateShipmentAction::ACTION_NAME, 'config' => []],
                $context
            );
        }

        if (!$this->flowExists($createdId, $context)) {
            $templateId = $this->createMailTemplate(self::CREATED_MAIL_TYPE, ['en-GB' => 'Shipbubble: shipment created', 'de-DE' => 'Shipbubble: Sendung erstellt'], self::createdMail(), $context);
            $this->createFlow(
                $createdId,
                'Shipbubble: tracking email to customer',
                ShipmentCreatedEvent::EVENT_NAME,
                'Emails the customer the courier and tracking link when the Shipbubble shipment is booked.',
                self::mailSequence($templateId, ['type' => 'default', 'data' => []]),
                $context
            );
        }

        if (!$this->flowExists($failedId, $context)) {
            $templateId = $this->createMailTemplate(self::FAILED_MAIL_TYPE, ['en-GB' => 'Shipbubble: booking failed', 'de-DE' => 'Shipbubble: Buchung fehlgeschlagen'], self::failedMail(), $context);
            $this->createFlow(
                $failedId,
                'Shipbubble: booking failed alert',
                ShipmentFailedEvent::EVENT_NAME,
                'Emails the shop admin when a Shipbubble shipment could not be booked.',
                self::mailSequence($templateId, ['type' => 'admin', 'data' => []]),
                $context
            );
        }
    }

    private function flowExists(string $id, Context $context): bool
    {
        return $this->flowRepository->searchIds(new Criteria([$id]), $context)->firstId() !== null;
    }

    private function actionInUse(Context $context): bool
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('sequences.actionName', CreateShipmentAction::ACTION_NAME))
            ->setLimit(1);

        return $this->flowRepository->searchIds($criteria, $context)->firstId() !== null;
    }

    /**
     * @param array{actionName: string, config: array<string, mixed>} $sequence
     */
    private function createFlow(string $id, string $name, string $eventName, string $description, array $sequence, Context $context): void
    {
        $this->flowRepository->create([[
            'id' => $id,
            'name' => $name,
            'eventName' => $eventName,
            'priority' => 1,
            'active' => true,
            'description' => $description,
            'sequences' => [['id' => Uuid::randomHex(), 'position' => 1, 'displayGroup' => 1] + $sequence],
        ]], $context);
    }

    /**
     * @param array{type: string, data: array<mixed>} $recipient
     *
     * @return array{actionName: string, config: array<string, mixed>}
     */
    private static function mailSequence(string $mailTemplateId, array $recipient): array
    {
        return [
            'actionName' => SendMailAction::ACTION_NAME,
            'config' => [
                'mailTemplateId' => $mailTemplateId,
                'documentTypeIds' => [],
                'recipient' => $recipient,
                'replyTo' => null,
            ],
        ];
    }

    /**
     * @param array<string, string> $typeNames
     * @param array<string, array<string, string>> $translations
     */
    private function createMailTemplate(string $technicalName, array $typeNames, array $translations, Context $context): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName))->setLimit(1);
        $typeId = $this->mailTemplateTypeRepository->searchIds($criteria, $context)->firstId();

        if ($typeId === null) {
            $typeId = Uuid::randomHex();
            $this->mailTemplateTypeRepository->create([[
                'id' => $typeId,
                'technicalName' => $technicalName,
                'availableEntities' => ['order' => 'order'],
                'translations' => array_map(static fn (string $name): array => ['name' => $name], $typeNames),
            ]], $context);
        }

        $templateId = Uuid::randomHex();
        $this->mailTemplateRepository->create([[
            'id' => $templateId,
            'mailTemplateTypeId' => $typeId,
            'systemDefault' => false,
            'translations' => array_map(
                static fn (array $translation): array => ['senderName' => '{{ salesChannel.translated.name }}'] + $translation,
                $translations,
            ),
        ]], $context);

        return $templateId;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function createdMail(): array
    {
        return [
            'en-GB' => [
                'subject' => 'Your order {{ order.orderNumber }} is booked with {{ courierName }}',
                'description' => 'Sent to the customer when the Shipbubble shipment is booked.',
                'contentHtml' => '<p>Hello {{ order.orderCustomer.firstName }},</p>'
                    . '<p>Your order <strong>{{ order.orderNumber }}</strong> has been handed to the courier <strong>{{ courierName }}</strong>.</p>'
                    . '<p><a href="{{ trackingUrl }}">Track your delivery</a></p>',
                'contentPlain' => "Hello {{ order.orderCustomer.firstName }},\n\n"
                    . "Your order {{ order.orderNumber }} has been handed to the courier {{ courierName }}.\n\n"
                    . 'Track your delivery: {{ trackingUrl }}',
            ],
            'de-DE' => [
                'subject' => 'Ihre Bestellung {{ order.orderNumber }} ist bei {{ courierName }} gebucht',
                'description' => 'Wird an den Kunden gesendet, wenn die Shipbubble-Sendung gebucht ist.',
                'contentHtml' => '<p>Hallo {{ order.orderCustomer.firstName }},</p>'
                    . '<p>Ihre Bestellung <strong>{{ order.orderNumber }}</strong> wurde an den Kurier <strong>{{ courierName }}</strong> übergeben.</p>'
                    . '<p><a href="{{ trackingUrl }}">Sendung verfolgen</a></p>',
                'contentPlain' => "Hallo {{ order.orderCustomer.firstName }},\n\n"
                    . "Ihre Bestellung {{ order.orderNumber }} wurde an den Kurier {{ courierName }} übergeben.\n\n"
                    . 'Sendung verfolgen: {{ trackingUrl }}',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function failedMail(): array
    {
        return [
            'en-GB' => [
                'subject' => 'Shipbubble shipment for order {{ order.orderNumber }} could not be booked',
                'description' => 'Sent to the shop admin when booking a Shipbubble shipment fails.',
                'contentHtml' => '<p>Booking the Shipbubble courier for order <strong>{{ order.orderNumber }}</strong> failed.</p>'
                    . '<p>Shipbubble said: <strong>{{ errorMessage }}</strong></p>'
                    . '<p>Fix the cause (for example top up your Shipbubble wallet), then open the order in the Administration and click <em>Create shipment</em>.</p>',
                'contentPlain' => "Booking the Shipbubble courier for order {{ order.orderNumber }} failed.\n\n"
                    . "Shipbubble said: {{ errorMessage }}\n\n"
                    . 'Fix the cause (for example top up your Shipbubble wallet), then open the order in the Administration and click Create shipment.',
            ],
            'de-DE' => [
                'subject' => 'Shipbubble-Sendung für Bestellung {{ order.orderNumber }} konnte nicht gebucht werden',
                'description' => 'Wird an den Shop-Admin gesendet, wenn die Buchung einer Shipbubble-Sendung fehlschlägt.',
                'contentHtml' => '<p>Die Buchung des Shipbubble-Kuriers für Bestellung <strong>{{ order.orderNumber }}</strong> ist fehlgeschlagen.</p>'
                    . '<p>Shipbubble meldet: <strong>{{ errorMessage }}</strong></p>'
                    . '<p>Beheben Sie die Ursache (z. B. Shipbubble-Wallet aufladen) und klicken Sie dann in der Bestellung in der Administration auf <em>Sendung erstellen</em>.</p>',
                'contentPlain' => "Die Buchung des Shipbubble-Kuriers für Bestellung {{ order.orderNumber }} ist fehlgeschlagen.\n\n"
                    . "Shipbubble meldet: {{ errorMessage }}\n\n"
                    . 'Beheben Sie die Ursache (z. B. Shipbubble-Wallet aufladen) und klicken Sie dann in der Bestellung in der Administration auf Sendung erstellen.',
            ],
        ];
    }
}
