<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use AppBundle\Entity\User;
use AppBundle\Entity\Annonce;

class AnnoncesController extends Controller
{
	private $session;
  private $repository;
  private $em;
	
  /**
  	* @Route("/annonce", name="/annonce")
  	*/

	public function annonceAction(Request $request){
		$this->init($request);
		if($this->session->has('id')){
			$array = array();
			$array['login']=$this->getLogin();
			return $this->render('default/annonce.html.twig',array( 'array' => $array));
		}else{
			return $this->redirectToRoute("homepage");
		}
	}

	private function generateUniqueFileName(){
        return md5(uniqid());
    }


	/**
  	* @Route("/annonce/deposer", name="/annonce/deposer")
  	*/
	public function deposerAction(Request $request){
		$this->init($request);
		if($this->session->has('id')){
			$array = array();
			$dir="uploads/images";
			$array['login']=$this->getLogin();
			$annonce=new Annonce();//creation form pour deposer une annonce
			$formAnnonce = $this->createFormBuilder($annonce)
			->add('categorie',ChoiceType::class, array(
				'choices' =>array(
					'Informatique' => 'Informatique',
					'Vehicules'=>'Vehicules',
					'Loisir'=>'loisir',
					'Immobilier'=>'Immobilier',
					'Habillement'=>'Habillement',
					'Autre'=>'Autre')))
			->add('titre', TextType::class)
			->add('description', TextareaType::class, array('attr' => array('cols' => '70', 'rows' => '10')))
			->add('prix', MoneyType::class)
			->add('DateDisponibilite', DateType::class, array('widget' => 'single_text','input' =>'string','format' => 'yyyy-MM-dd',))
			->add('photo1', FileType::class, array('label' => ' (JPEG file)'))
			->add('save', SubmitType::class, array('label' => 'Valider'))
      ->getForm();
      $formAnnonce->handleRequest($request);
      if($formAnnonce->isSubmitted() && $formAnnonce->isValid()){//depot de l'annonce dans la BD
        $annonce->setDateDisponibilite(new \DateTime($annonce->getDateDisponibilite()));
        $url = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',2)),0,10);
				$annonce->setUrl($url);
				$annonce->setDisponibilite(1);
				$file=$annonce->getPhoto1();
        $fileName=$this->generateUniqueFileName().'.'.$file->guessExtension();
        $file->move($dir,$fileName);
        $photo="uploads/images/".$fileName;
        $annonce->setPhoto1($photo);
		    $champs['id']=$this->repUser->findOneById($this->session->get('id'))->getId();
		    $user=$this->repUser->findOneBy(array('id'=>$champs['id']));
		    $annonce->setIdUser($user);
		    $this->em = $this->getDoctrine()->getManager();
		    $this->em->persist($annonce);
		    $this->em->flush();
		    $array['ok']="Annonce déposée!";
		    return  $this->render('default/annonce.html.twig',array( 'array' => $array));
      }
      return $this->render('default/annonce/deposer.html.twig', array('array'=>$array, 'annonce'=>$formAnnonce->createView()));
		}else{
			return $this->redirectToRoute("homepage");
		}
	}

	/**
    * @Route("/annonce/voir", name="/annonce/voir")
    */
	//afficher toutes  les annonces disponibles
	public function voirAction(Request $request){
		$this->init($request);
		if($this->session->has('id')){
			$array = array();
			$array['login']=$this->getLogin();
			$annonces=$this->allAnnonce();
			foreach ($annonces as $annonce) {//j'affiche les details d'une annonce
				$url=$annonce->getUrl();
				if($url==$request->query->get('url')){
					$ann=$this->repAnnonce->findOneByUrl($url);
					$user=$ann->getIdUser();
					return $this->render('default/annonce/voir.html.twig',array('ann' =>$ann, 'array' => $array,'user' => $user)); //j'affiche la page avec les details d'unne annonce
				}
			}
			return $this->render('default/annonce/voir.html.twig',array('annonces' =>$annonces, 'array' => $array));
		}else{
			return $this->redirectToRoute("homepage");
		}
	}

	public function getLogin(){
    return $this->repUser->findOneById($this->session->get('id'))->getLogin();
  }

	public function getId(){
    return $this->repUser->findOneById($this->session->get('id'))->getId();
  }

  // requete pour avoir toutes les annonces disponibles
  public function allAnnonce(){
    $annonces=$this->repAnnonce->findBy(['disponibilite' =>'1']);
    return $annonces;
  }

	public function init(Request $request){
    $this->session = $request->getSession();
    $this->repUser = $this->getDoctrine()->getRepository('AppBundle:User');
    $this->repAnnonce = $this->getDoctrine()->getRepository('AppBundle:Annonce');
    $this->em = $this->getDoctrine()->getManager();
  }

  //afficher details d'une annonce avec son url
  /**
  	* @Route("/annonce/voir/{url}", name="voirAnnonce")
  	*/

  //modifier une annonce (enc cours)
    /**
     * @Route("/annonce/modifier", name="/annonce/modifier")
    */
    public function modifierAction(Request $request)
    {
    	$this->init($request);
			if($this->session->has('id')){
				$array = array();
				$array['login']=$this->getLogin();
				if($request->query->get('url')){
					$annonce=$this->repAnnonce->findOneByUrl($request->query->get('url'));
					if($annonce==null){
						$annonces=$this->repAnnonce->findBy(['idUser' =>$this->getId()]);
						return $this->render('default/annonce/mesAnnonces.html.twig',array( 'array' => $array, 'annonces' =>$annonces));
					}
					$formAnnonce = $this->createFormBuilder($annonce)
						->add('categorie',ChoiceType::class, array(
							'choices' =>array(
								' '=>null,
								'Informatique' => 'Informatique',
								'Vehicules'=>'Vehicules',
								'Loisir'=>'loisir',
								'Immobilier'=>'Immobilier')))
						->add('titre', TextType::class)
						->add('description', TextareaType::class, array('attr' => array('cols' => '70', 'rows' => '10')))
						->add('prix', MoneyType::class)
						->add('disponibilite',ChoiceType::class, array('choices_as_values' => true,'multiple'=>false,'expanded'=>true,'choices' =>array('oui'=>1,'non'=>0)))
						->add('save', SubmitType::class, array('label' => 'Valider'))
			      ->getForm();
			      $formAnnonce->handleRequest($request);
      			if($formAnnonce->isSubmitted() && $formAnnonce->isValid()){
      				$this->em = $this->getDoctrine()->getManager();
		        	$this->em->persist($annonce);
		        	$this->em->flush();
		        	$array['ok']="Annonce modifiée!";
		        	return $this->render('default/annonce.html.twig',array( 'array' => $array));
      			}
			      return $this->render('default/annonce/modifier.html.twig',array('array'=>$array, 'annonce'=>$formAnnonce->createView()));
					}else{
						return $this->render('default/annonce.html.twig',array( 'array' => $array));
				}
			}else{
				return $this->redirectToRoute("homepage");
			}
    }

    //mes annonces
    /**
     * @Route("/annonce/mesAnnonces", name="/annonce/mesAnnonces")
    */
    public function mesAnnoncesAction(Request $request){
    	$this->init($request);
			if($this->session->has('id')){
				$array = array();
				$array['login']=$this->getLogin();
				if($request->query->get('url')){
							$ann=$this->repAnnonce->findOneByUrl($request->query->get('url'));
							if($ann==null){
								$annonces=$this->repAnnonce->findBy(['idUser' =>$this->getId()]);
								return $this->render('default/annonce/mesAnnonces.html.twig',array( 'array' => $array, 'annonces' =>$annonces)); 
							}
							$user=$ann->getIdUser();
							return $this->render('default/annonce/mesAnnonces.html.twig',array('ann' =>$ann, 'array' => $array,'user' => $user)); //j'affiche la page avec les details d'unne annonce
				}else{
					$annonces=$this->repAnnonce->findBy(['idUser' =>$this->getId()]);
					return $this->render('default/annonce/mesAnnonces.html.twig',array( 'array' => $array, 'annonces' =>$annonces));
				}
			}else{
					return $this->redirectToRoute("homepage");
			}
    }

    /**
     * @Route("/annonce/photo", name="/annonce/photo")
    */
    public function photoAction(Request $request){
    	$this->init($request);
			if($this->session->has('id')){
				$array = array();
				$dir="uploads/images";
				$array['login']=$this->getLogin();
				$annonce=$this->repAnnonce->findOneByUrl($request->query->get('url'));
				if($annonce==null){
					$annonces=$this->repAnnonce->findBy(['idUser' =>$this->getId()]);
					return $this->render('default/annonce/mesAnnonces.html.twig',array( 'array' => $array, 'annonces' =>$annonces));
				}
				$formPhoto=$this->createFormBuilder($annonce)
					->add('photo1', FileType::class, array('required'=> false,'label' => ' (JPEG file)','data_class' => null,))
					->add('save',SubmitType::class, array('label' => 'Validez'))
          ->getForm();
        $formPhoto->handleRequest($request);
        if($formPhoto->isSubmitted() && $formPhoto->isValid()){
          $file=$annonce->getPhoto1();
          $fileName=$this->generateUniqueFileName().'.'.$file->guessExtension();
          $file->move($dir,$fileName);
          $photo="uploads/images/".$fileName;
          $annonce->setPhoto1($photo);
          $this->em = $this->getDoctrine()->getManager();
          $this->em->persist($annonce);
          $this->em->flush();
          $array['ok']="photo ajoutée";
          return $this->render('default/annonce.html.twig',array( 'array' => $array));
        }
          return $this->render('default/annonce/modifier.html.twig',array('array'=>$array, 'photo'=>$formPhoto->createView()));
			}else{
				return $this->redirectToRoute("homepage");
			}
    }
    
}
