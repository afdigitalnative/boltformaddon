<?php

declare(strict_types=1);

namespace Webdevchampion\BoltformaddonExtension;

use Bolt\Extension\BaseExtension;
use Symfony\Component\Routing\Route;
use Symfony\Component\Form\FormInterface;
use Bolt\BoltForms\Event\BoltFormsEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Bolt\BoltForms\Event\BoltFormsEvent;
use Bolt\Storage\Entity;
use Doctrine\ORM\EntityManager;
use Bolt\Repository\ContentRepository;
use Bolt\Entity\Content;
use Bolt\BoltForms\Event\PostSubmitEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\EventListener\MessageListener;
use Twig\Environment as TwigEnvironment;
use Symfony\Component\Mime\Email;

class Extension extends BaseExtension
{
	private $messages = [];
	
    /**
     * Return the full name of the extension
     */
    public function getName(): string
    {
        return 'Boltform addon for Vetrack CRM integration';
    }

    /**
     * Add the routes for this extension.
     *
     * Note: These are cached by Symfony. If you make modifications to this, run
     * `bin/console cache:clear` to ensure your routes are parsed.
     */
    public function getRoutes(): array
    {
        return [
            'boltformaddon' => new Route(
                '/extensions/boltformaddon/{name}',
                ['_controller' => 'Webdevchampion\BoltformaddonExtension\Controller::index'],
                ['name' => '[a-zA-Z0-9]+']
            ),
        ];
    }

    /**
     * Ran automatically, if the current request is in a browser.
     * You can use this method to set up things in your extension.
     *
     * Note: This runs on every request. Make sure what happens here is quick
     * and efficient.
     */
    public function initialize($cli = false): void
    {
        //$this->addWidget(new BoltformaddonWidget());

        $this->addTwigNamespace('boltformaddon-extension');

        //$this->addListener('kernel.response', [new EventListener(), 'handleEvent
		
		$this->addListener(BoltFormsEvents::POST_SET_DATA, array($this, 'populateCourses'));	
		$this->addListener(BoltFormsEvents::PRE_SUBMIT, array($this, 'sendToVetrack'));
		$this->addListener(BoltFormsEvents::POST_SUBMIT, array($this, 'sendCourseConfirmMail'));	
		$this->addListener(BoltFormsEvents::POST_SUBMIT, array($this, 'sendConfirmationMail'));
    }
	
	public function populateCourses(FormEvent $event): void
	{
		$data = $event->getData();
		$event = $event->getEvent();
		$form = $event->getForm();
		
		if($form->getName() === 'courses') {
			$courseSelect = $form->get('courseselect');
			$courseSelectOptions = $courseSelect->getConfig()->getOptions();
			$courses = iterator_to_array($this->getQuery()->getContent('main-courses')->getCurrentPageResults());
			$courseSelectOptions['choices'] = [];
						
			foreach($courses as $course) {
				$courseSelectOptions['choices'][$course->getFieldValue('title')] = $course->getFieldValue('slug');	
			}

			$form->add('courseselect', ChoiceType::class, $courseSelectOptions);
		}		
	
	}

    public function sendToVetrack(FormEvent $event): void
    {
		$data = $event->getData();
		$event = $event->getEvent();
		$form = $event->getForm();
		
		if($form->getName() === 'courses') {
			$sClie_Surname = $data['last'];
			$sClie_Given = $data['first'];
			$email = $data['email'];
			$dob = strtotime($data['dob']);
			$xsdClie_DOB = !is_bool($dob) ? Date('Y-m-d', $dob) : '1970-01-01';

			$file = fopen("test.txt","w");	
				
			if(strlen($sClie_Given) >= 2 && strlen($sClie_Surname) >= 2) {
				$VETAPIUrl = "https://trainerportal.org.au/VETtrakAPI/VT_API.asmx?wsdl";
				$Client = new \SoapClient($VETAPIUrl);
				$Client->TAuthenticate = $Client->API_Handshake();

				// Validate client and Get Token
				$Credentials = new \stdClass; 
				$Credentials->sUsername = "xxxx";
				$Credentials->sPassword = "xxxx";
				$Client->TAuthenticate = $Client->ValidateClient($Credentials);

				// Add student to Vettrak Database
				$GetTokenObject = new \stdClass;               
				$GetTokenObject->sToken = $Client->TAuthenticate->ValidateClientResult->Token;
				$GetTokenObject->sClie_Surname = $sClie_Surname;
				$GetTokenObject->sClie_Given = $sClie_Given;
				$GetTokenObject->email = $email;
				$GetTokenObject->xsdClie_DOB = $xsdClie_DOB; //'2021-11-28T16:30:09.000';
				$GetTokenObject->divisionId = 0;

				$Client->TAuthClie = $Client->AddClientAfterCheck($GetTokenObject);

				$ReturnGiven = $Client->TAuthClie->AddClientAfterCheckResult->Clie->Clie_Given;
				$ReturnSurname = $Client->TAuthClie->AddClientAfterCheckResult->Clie->Clie_Surname;
				$ReturnCode = $Client->TAuthClie->AddClientAfterCheckResult->Clie->Clie_Code;
				$ReturnStatus = $Client->TAuthClie->AddClientAfterCheckResult->Auth->StatusMessage;	
				
				fwrite($file, 'valid'. $ReturnGiven . '/' . $ReturnCode . '/' . $ReturnStatus);				
				
				$data['studentid'] = $ReturnCode;
				$event->setData($data);				
				
			} else {
				fwrite($file, 'invalid');
			}
			
			fclose($file);
		}
		
	}
	
    public function sendCourseConfirmMail(FormEvent $event): void
    {
		$data = $event->getData();
		$event = $event->getEvent();
		$form = $event->getForm();
		
		if($form->getName() === 'courses') {

			if($form->isSubmitted() && $form->isValid()) {
				
				$to = $data['email'];
				$subject = "Enrollment Inquiry - Confirmation Email";

				$message_text = file_get_contents('./emails/'.$form->getName().'.html', true);
				$message = "
					<html>
					<head>
						<title>Enrollment Inquiry - Confirmation Email</title>
					</head>
					<body>
					<table style='border-collapse: collapse;'>
						<tr>
							<td style='border: unset'>".$message_text."</td>
						</tr>
						<tr>
							<td style='border: unset'><strong>Your Student ID: ".$data['studentid']."</td>
						</tr>
					</table>
					</body>
					</html>
				";
			
				// Always set content-type when sending HTML email
				$headers = "MIME-Version: 1.0" . "\r\n";
				$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
				// More headers
				$headers .= 'From: VICSEG New Futures Website <website@vicsegnewfutures.org.au>' . "\r\n";
				mail($to,$subject,$message,$headers);
				
			}
		}
	}	
	
    public function sendConfirmationMail(FormEvent $event): void
    {
		$data = $event->getData();
		$event = $event->getEvent();
		$form = $event->getForm();
				
		if($form->getName() !== 'courses') {
			if($form->isSubmitted() && $form->isValid()) {
				$file = fopen("confirmmaillog.txt","w");			
				$to = $data['email'];
				$subject = "Confirmation Mail";

				$message = file_get_contents('./emails/'.$form->getName().'.html', true);

				// Always set content-type when sending HTML email
				$headers = "MIME-Version: 1.0" . "\r\n";
				$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

				// More headers
				$headers .= 'From: VICSEG New Futures Website <website@vicsegnewfutures.org.au>' . "\r\n";
				fwrite($file, $message);
				
				mail($to,$subject,$message,$headers);	
				
				fclose($file);
			}
		}
	}		

	
    /**
     * Ran automatically, if the current request is from the command line (CLI).
     * You can use this method to set up things in your extension.
     *
     * Note: This runs on every request. Make sure what happens here is quick
     * and efficient.
     */
    public function initializeCli(): void
    {
    }
}
