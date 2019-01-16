<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use AppBundle\Entity\User;
use AppBundle\Entity\Annonce;
use AppBundle\Entity\Message;
class MessagesController extends Controller
{
	private $session;
    private $repository;
    private $em;
	/**
     * @Route("/messagerie", name="/messagerie")
     */

	public function messagerieAction(Request $request)
	{
		$this->init($request);
		if($this->session->has('id')){
			$array = array();
			$array['login']=$this->getLogin();
            $msgs=$this->allMessage();
            foreach ($msgs as $msg) {//j'affiche les details d'un message
                $iduser=$msg->getId();
                if($iduser==$request->query->get('url')){
                    $msgDetails=$this->repMessage->findOneById($iduser);
                    if($msgDetails!=null){
                    $userDestinataire=$this->repUser->findOneById($msgDetails->getIdDestinataire())->getLogin();
                        return $this->render('default/messagerie.html.twig',array('msg' =>$msgDetails, 'array' => $array,'userDestinataire' => $userDestinataire,'nbmsgNonlu'=>$this->compteMessageNonLu())); //j'affiche la page avec les details d'un message
                    }else{
                        return $this->redirectToRoute('bienvenue');
                    }
                }
            }
			return $this->render('default/messagerie.html.twig',array( 'array' => $array,'nbmsgNonlu'=>$this->compteMessageNonLu()));
		}else{
			return $this->redirectToRoute("homepage");
		}
	}

    public function compteMessageNonLu()
    {
        $msgNonLu=$this->repMessage->findBy(['idDestinataire' =>$this->getId(),'status'=>'non lu']);
        return count($msgNonLu);
    }

    public function allMessage()
    {
        return $this->repMessage->findAll();
    }

	public function getLogin(){
        return $this->repUser->findOneById($this->session->get('id'))->getLogin();
    }

    public function getId()
    {
        return $this->repUser->findOneById($this->session->get('id'))->getId();
    }

	public function init(Request $request){
        $this->session = $request->getSession();
        $this->repUser = $this->getDoctrine()->getRepository('AppBundle:User');
        $this->repAnnonce = $this->getDoctrine()->getRepository('AppBundle:Annonce');
        $this->repMessage = $this->getDoctrine()->getRepository('AppBundle:Message');
        $this->em = $this->getDoctrine()->getManager();
    }

    /**
     * @Route("/nouveau", name="/nouveau")
     */

    public function nouveauAction(Request $request)
    {
    	$this->init($request);
		if($this->session->has('id')){
            $array = array();
            $array['login']=$this->getLogin();
            $message=new Message();//creation form pour deposer une annonce
            if($request->query->get('url')){
                $array['iddes']=$request->query->get('url');
                $logindes=$this->repUser->findOneByLogin($request->query->get('url'));
                if($logindes!=null){
                    $iddesti=$logindes->getId();
                    $formMessageTo=$this->createFormBuilder($message)
                    ->add('objet', TextType::class)
                    ->add('msg', TextareaType::class, array('attr' => array('cols' => '70', 'rows' => '10')))
                    ->add('save', SubmitType::class, array('label' => 'Envoyer'))
                    ->getForm();
                    $formMessageTo->handleRequest($request);
                    if($formMessageTo->isSubmitted() && $formMessageTo->isValid()){
                        $message->setIdDestinataire($iddesti);
                        $message->setStatus("non lu");
                        $message->setIdUser($this->repUser->findOneById($this->session->get('id')));
                        $this->em = $this->getDoctrine()->getManager();
                        $this->em->persist($message);
                        $this->em->flush();
                        $array['ok']="Message envoyé!";
                        return $this->render('default/messagerie.html.twig',array( 'array' => $array,'nbmsgNonlu'=>$this->compteMessageNonLu()));
                    }
                    return $this->render('default/messagerie/nouveau.html.twig',array('array' => $array,'messageTo'=>$formMessageTo->createView(),'nbmsgNonlu'=>$this->compteMessageNonLu()));
                }else{
                    return $this->redirectToRoute("/bienvenue");
                }
            }

            $formMessage = $this->createFormBuilder($message)
            ->add('id_destinataire', EntityType::class, array('class' => User::class,'choice_label' => 'login','label'=>'destinataire'))
            ->add('objet', TextType::class)
            ->add('msg', TextareaType::class, array('attr' => array('cols' => '70', 'rows' => '10')))
            ->add('save', SubmitType::class, array('label' => 'Envoyer'))
            ->getForm();
            $formMessage->handleRequest($request);
            if($formMessage->isSubmitted() && $formMessage->isValid()){
                $destId=$message->getIdDestinataire()->getId();
                $message->setIdDestinataire($destId);
                $message->setStatus("non lu");
                $message->setIdUser($this->repUser->findOneById($this->session->get('id')));
                $this->em = $this->getDoctrine()->getManager();
                $this->em->persist($message);
                $this->em->flush();
                $array['ok']="Message envoyé!";
                return $this->render('default/messagerie.html.twig',array( 'array' => $array,'nbmsgNonlu'=>$this->compteMessageNonLu()));
            }
            return $this->render('default/messagerie/nouveau.html.twig',array('array' => $array,'message'=>$formMessage->createView(),'nbmsgNonlu'=>$this->compteMessageNonLu()));
            
		}else{
			return $this->redirectToRoute("homepage");
		}
    }

    /**
     * @Route("/recu", name="/recu")
     */
    public function recuAction(Request $request)
    {
    	$this->init($request);
		if($this->session->has('id')){
			$array = array();
			$array['login']=$this->getLogin();
            $msgNonLu=$this->repMessage->findBy(['idDestinataire' =>$this->getId(),'status'=>'non lu']);
            $msgLu=$this->repMessage->findBy(['idDestinataire' =>$this->getId(),'status'=>'lu']);
            $msgs=$this->allMessage();
            foreach ($msgs as $msg) {//j'affiche les details d'un message
                $idmsg=$msg->getId();
                if($idmsg==$request->query->get('url')){
                    $msgDetails=$this->repMessage->findOneById($idmsg);
                    if($msgDetails!=null){
                        if($msgDetails->getStatus()=="non lu"){
                            $msgDetails->setStatus("lu");
                            $this->em = $this->getDoctrine()->getManager();
                            $this->em->persist($msgDetails);
                            $this->em->flush();
                        }
                        $userExpediteur=$this->repUser->findOneById($msgDetails->getIdUser())->getLogin();
                        return $this->render('default/messagerie/recu.html.twig',array('msgDetails' =>$msgDetails, 'array' => $array,'userExpediteur' => $userExpediteur,'nbmsgNonlu'=>$this->compteMessageNonLu())); //j'affiche la page avec les details d'un message
                    }else{
                        return $this->redirectToRoute("/bienvenue");
                    }
                }
            }
			return $this->render('default/messagerie/recu.html.twig',array( 'array' => $array,'nbmsgNonlu'=>$this->compteMessageNonLu(),'msgNonLu'=>$msgNonLu,'msgLu'=>$msgLu));
		}else{
			return $this->redirectToRoute("homepage");
		}
    }

    /**
     * @Route("/envoye", name="/envoye")
     */
    public function envoyeAction(Request $request)
    {
    	$this->init($request);
		if($this->session->has('id')){
			$array = array();
			$array['login']=$this->getLogin();
            $mesmsg=$this->repMessage->findBy(['idUser' =>$this->getId()]);
			return $this->render('default/messagerie/envoye.html.twig',array( 'array' => $array,'mesmsg'=>$mesmsg,'nbmsgNonlu'=>$this->compteMessageNonLu()));
		}else{
			return $this->redirectToRoute("homepage");
		}
    }

}
