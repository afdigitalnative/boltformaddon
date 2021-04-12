<?php

declare(strict_types=1);

namespace Webdevchampion\BoltformaddonExtension;

use Bolt\BoltForms\Event\PostSubmitEvent;
use Bolt\BoltForms\Factory\EmailFactory;
use Bolt\Common\Str;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Tightenco\Collect\Support\Collection;
//use Bolt\BoltForms\Event\BoltFormsEvents;
use Symfony\Component\Form\FormEvent;
//use Symfony\Component\Form\FormEvents;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class Mailer implements EventSubscriberInterface
{
    private $mailer;
	private $subjects = [
		'contact' => 'General Inquiry - Confirmation Email',
		'elc' => 'ELC Inquiry - Confirmation Email',
		'fdc' => 'Family Day Care - Confirmation Email',
		'courses' => 'Enrollment Inquiry - Confirmation Email'
	];

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function handleEvent(PostSubmitEvent $event): void
    {		
        if (!$form->isValid()) {
            return;
        }		
		
		$file = fopen("testmailer.txt","a");
		fwrite($file, 'valid');
		fclose($file);

		$form = $event->getForm();
		$data = $form->getData();		
		$meta = $event->getMeta();
		
        $email = (new TemplatedEmail())
            ->from($this->getFrom())
            ->to($data['email'])
            ->subject($this->subjects[$form->getName()])
            ->htmlTemplate('/forms/'.$form->getName().'_confirm_email.html.twig')
            ->context([
                'data' => $form->getData(),
                'formname' => $form->getName(),
                'meta' => $meta
            ]);		
				
		$this->mailer->send($email);
    }

    protected function getFrom(): Address
    {
        return $this->getAddress('vicseg@simplecreatif.com', 'VICSEG New Futures Website');
    }

    private function getAddress(string $email, string $name): Address
    {
        $email = $this->parsePartial($email);
        $name = $this->parsePartial($name);

        return new Address($email, $name);
    }

    private function parsePartial(string $partial): string
    {
        $fields = $this->form->getData();

        return Str::placeholders($partial, $fields);
    }

    public static function getSubscribedEvents()
    {
        return [
            'boltforms.post_submit' => ['handleEvent', 40],
        ];
    }
}
