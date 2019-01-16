<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\AbstractType;
use AppBundle\Entity\User;
use AppBundle\Entity\Annonce;
use AppBundle\Entity\Evaluation;

class DefaultController extends Controller
{
    private $session;
    private $repository;
    private $em;



    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $this->init($request);

        // si on n'est pas connecté
        if(!$this->session->has('id')){
            $connexion= $this->createFormConnexion(array(""));
            $inscription= $this->createFormInscription(array(""));
            $array= array();
            // si champs envoyé
            if(isset($request->request->all()['form'])){
                    $champs = $request->request->all()['form'];
                    if(count($champs) == count($inscription)+1){
                        if($this->inscription($champs) == null){
                            $array['reussiIns'] = "Inscription Reussie";
                        }else{
                            $inscription = $this->createFormInscription($champs);
                            $array['erreurIns'] = $this->inscription($champs);
                        }
                    }if(count($champs)==count($connexion)+1){
                        if($this->connexion($champs)==null){
                            return $this->redirectToRoute("/bienvenue");
                        }else{
                            $connexion=$this->createFormConnexion($champs);
                            $array['erreurCon']=$this->connexion($champs);
                        }
                    }
                    
                }

            $array['inscription']=$inscription->createView();
            $array['connexion']=$connexion->createView();
            return $this->render('default/index.html.twig',array('array'=>$array));
        }else{
            //si connecté
            
            return $this->redirectToRoute("/bienvenue");
        }
    }

     //CREATION DE FORMULAIRES
    public function createFormInscription(Array $champs){
        $dataLogin = $this->hydrateChamps($champs, array("login"));
        $dataPassword = $this->hydrateChamps($champs, array("password"));
        $dataPassword2 = $this->hydrateChamps($champs, array("password2"));
        $form = $this->createFormBuilder()          
            ->add('login', TextType::class, array('data'=> $dataLogin,'attr'=>array('placeholder'=>'Entrez votre login')))
            ->add('password', PasswordType::class, array('data'=>$dataPassword,'attr'=>array('placeholder'=>'Entrez votre mot de passe')))
            ->add('password2', PasswordType::class, array('data'=>$dataPassword2,'attr'=>array('placeholder'=>'Entrez encore votre mot de passe')))
            ->add('save', SubmitType::class, array('label' => 'Inscription'))
            ->getForm();
        return $form;
    }
    
    public function createFormConnexion(Array $champs){
        $dataLogin = $this->hydrateChamps($champs, array("login"));
        $dataPassword = $this->hydrateChamps($champs, array("password"));
        $form = $this->createFormBuilder()
            ->add('login', TextType::class, array('data'=>$dataLogin,'attr'=>array('placeholder'=>'Entrez votre login')))
            ->add('password', PasswordType::class, array('label' => 'Mot de passe', 'data'=>$dataPassword,'attr'=>array('placeholder'=>'Entrez votre mot de passe')))
            ->add('save', SubmitType::class, array('label' => 'Connexion'))
            ->getForm();
        return $form;
    }

    public function hydrateChamps(Array $champs, Array $value){
        if(count($champs)>1)
            return $champs[$value[0]];
        return "";
    }

    //fonction d'inscription
    public function inscription(Array $champs){
      if($this->repository->findOneByLogin($champs['login']))
        return "le login ".$champs['login']." est deja pris";
      else{
        if($champs['password']==$champs['password2']){
          $user = new User();
          $user->setLogin($champs['login']);
          $user->setPassword(md5($champs['password']));
          $this->em = $this->getDoctrine()->getManager();
          $this->em->persist($user);
          $this->em->flush();
          return null;
        }else{
          return "les 2 mots de passe doivent être les mêmes!";
        }   
      }
    }

    //fonction de connexion
    public function connexion(Array $champs){
        if($this->repository->findOneByLogin($champs['login']))
            if($this->repository->findOneBy(array('login'=>$champs['login'],'password'=>md5($champs['password'])))){
                $user=$this->repository->findOneBy(array('login'=>$champs['login'],'password'=>md5($champs['password'])));
                $this->session->set('id',$user->getId());
                return null;
            }else{
                return "le mot de passe est incorrect!";
            }
        else{
            return "le login est incorrect!";
        }

    }

    
    public function init(Request $request){
        $this->session = $request->getSession();
        $this->repEval = $this->getDoctrine()->getRepository('AppBundle:Evaluation');
        $this->repository = $this->getDoctrine()->getRepository('AppBundle:User');
        $this->repAnnonce = $this->getDoctrine()->getRepository('AppBundle:Annonce');
        $this->em = $this->getDoctrine()->getManager();
    }

    //page apres être connecté

    /**
     * @Route("/bienvenue", name="/bienvenue")
     */
    public function bienvenueAction(Request $request)
    {

        $this->init($request);
        if($this->session->has('id')){ //si on a une session sinon redirection vers page de connexion
            $array = array();
            $array['login']=$this->getLogin();
            $formSearch = $this->createFormBuilder()
              ->add('recherche', TextType::class,array('required'=> false,'attr'=>array('placeholder'=>'Que recherchez-vous?')))
              ->add('categorie',ChoiceType::class, array(
                'choices' =>array(
                  'Toutes catégories' => 'Toutes categories',
                  'Informatique' => 'Informatique',
                  'Véhicules'=>'Vehicules',
                  'Loisir'=>'loisir',
                  'Immobilier'=>'Immobilier',
                  'Habillement'=>'Habillement',
                  'Autre'=>'Autre')))
              ->add('save',SubmitType::class, array('label' => 'Rechercher'))
              ->getForm();
            $formSearch->handleRequest($request);
            if($formSearch->isSubmitted() && $formSearch->isValid()){
              $data = $formSearch->getData();
              if($data['recherche']==null){
                if($data['categorie']=='Toutes categories'){
                  $result=$this->repAnnonce->findBy(['disponibilite'=>1]);
                  return $this->render('default/bienvenue.html.twig',array('array' =>$array,'form'=>$formSearch->createView(),'result'=>$result));
                }else{
                  $result=$this->repAnnonce->findBy(['categorie' =>$data['categorie'],'disponibilite'=>1]);
                  return $this->render('default/bienvenue.html.twig',array('array' =>$array,'form'=>$formSearch->createView(),'result'=>$result));
                }
              }else{
                if($data['categorie']=='Toutes categories'){
                  $sql_query=$this->em->createQuery("SELECT o from AppBundle:Annonce o where o.disponibilite=1 and (o.titre like '%$data[recherche]%'  or o.description like '%$data[recherche]%')");
                  $result=$sql_query->getResult();
                  return $this->render('default/bienvenue.html.twig',array('array' =>$array,'form'=>$formSearch->createView(),'result'=>$result));
                }else{
                  $sql_query=$this->em->createQuery("SELECT o from AppBundle:Annonce o where o.disponibilite=1 and o.categorie='$data[categorie]' and (o.titre like '%$data[recherche]%'  or o.description like '%$data[recherche]%')");
                  $result=$sql_query->getResult();
                  return $this->render('default/bienvenue.html.twig',array('array' =>$array,'form'=>$formSearch->createView(),'result'=>$result));
                }
              } 
            }
            return $this->render('default/bienvenue.html.twig',array('array' =>$array,'form'=>$formSearch->createView()));
        }else{
            return $this->redirectToRoute("homepage");
        }
    }

    //fonction de deconnexion avec redirection a la page d'accueil
    /**
     * @Route("/deconnexion", name="/deconnexion")
     */
    public function deconnexionAction(Request $request)
    {
        $this->init($request);
        $this->session->clear();
        return $this->redirectToRoute("homepage");
    }

    private function generateUniqueFileName()
    {
        // md5() reduces the similarity of the file names generated by
        // uniqid(), which is based on timestamps
        return md5(uniqid());
    }

    // avoir le login
    public function getLogin()
    {
        return $this->repository->findOneById($this->session->get('id'))->getLogin();
    }

    //user avec infos et vote
    /**
     * @Route("/profil/moi", name="/profil/moi")
     */

    public function monProfilAction(Request $request)
    {
        $this->init($request);
        if($this->session->has('id')){
            $array = array();
            $array['login']=$this->getLogin();
            $user=$this->repository->findOneByLogin($this->getLogin());
            $res=0;
            $noteprofil=$this->repEval->findBy(['idDestinataire' =>$user->getId()]); 
            if (!$noteprofil) {
                    $array['res']='Pas encore de Note';
            }else{
                foreach ($noteprofil as $nt) {//j'affiche les details d'une annonce
                    $res=$res+$nt->getNote();
                }
                $array['res']=round($res/count($noteprofil), 2);
            }
            return $this->render('default/profil/moi.html.twig',array( 'array' => $array,'user'=>$user));
        }else{
            return $this->redirectToRoute("homepage");
        }
    }

    //route pour un profil
    /**
     * @Route("/profil", name="/profil")
     */
    public function voirAction(Request $request)
    {
        $this->init($request);
        if($this->session->has('id')){
            $array = array();
            $array['login']=$this->getLogin();
            $url=$request->query->get('url');
            if($url==$this->getLogin()){
              return $this->redirectToRoute("/profil/moi");
            }else{
              $user=$this->repository->findOneByLogin($url);
              //je recupere la note du profil et je le calcul;
              if($user!=null){
                $res=0;
                $noteprofil=$this->repEval->findBy(['idDestinataire' =>$user->getId()]); 
                if (!$noteprofil) {
                        $array['res']='Pas encore de Note';
                }else{
                    foreach ($noteprofil as $nt) {//j'affiche les details d'une annonce
                        $res=$res+$nt->getNote();
                    }
                    $array['res']=round($res/count($noteprofil), 2);
                }
                $note = new Evaluation();
                $form = $this->createFormBuilder($note)
                ->add('note', ChoiceType::class,array('choices'=> array('0'=>0, '1'=>1, '2'=>2,'3'=>3, '4'=>4, '5'=>5)))
                ->add('commentaire', TextareaType::class, array('attr' => array('cols' => '10', 'rows' => '10')))
                ->add('save',SubmitType::class, array('label' => 'Evaluez!'))
                ->getForm();
                //test si formulaire rempli
                $form->handleRequest($request);
                if($form->isSubmitted() && $form->isValid()){
                    $note->setIdDestinataire($user->getId());
                    $userVottant=$this->repository->findOneBy(array('id'=>$this->repository->findOneById($this->session->get('id'))->getId()));
                    $note->setIdUser($userVottant);
                    $this->em = $this->getDoctrine()->getManager();
                    $this->em->persist($note);
                    $this->em->flush();
                    $array['vote']="merci de la note!";
                    return $this->redirectToRoute("/bienvenue");
                }
                return $this->render('default/profil/voir.html.twig',array( 'array' => $array, 'user' => $user, 'form' => $form->createView()));
              }else{
                return $this->redirectToRoute("/bienvenue");
              }
            }
        }else{
            return $this->redirectToRoute("homepage");
        }
    }
    //route pour details d'une personne
    /**
     * @Route("/profil/{login}", name="voirProfil")
    */

    //modifier un profil
    /**
     * @Route("/profil/modifieMdp", name="/profil/modifieMdp")
     */

    public function modifieMdp(Request $request)
    {
        $this->init($request);
        $array = array(); 
        if($this->session->has('id')){
            $array['login']=$this->getLogin();
            $user=$this->repository->findOneByLogin($this->getLogin());
            $form = $this->createFormBuilder($user)
            ->add('password', RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options'  => array('label' => 'Nouveau Mot de Passe','attr'=>array('placeholder'=>'Entrez votre nouveau mot de passe')),
                'second_options' => array('label' => 'Repetez Nouveau Mot de Passe','attr'=>array('placeholder'=>'Entrez encore votre nouveau mot de passe')),
            ))
            ->add('save',SubmitType::class, array('label' => 'Validez'))
            ->getForm();
            //test si formulaire rempli
            $form->handleRequest($request);
            if($form->isSubmitted() && $form->isValid()){
                $user->setPassword(md5($user->getPassword()));//code le mdp en md5
                $this->em = $this->getDoctrine()->getManager();
                $this->em->persist($user);
                $this->em->flush();
                $res=0;
                $noteprofil=$this->repEval->findBy(['idDestinataire' =>$user->getId()]); 
                if (!$noteprofil) {
                        $array['res']='Pas encore de Note';
                }else{
                    foreach ($noteprofil as $nt) {//j'affiche les details d'une annonce
                        $res=$res+$nt->getNote();
                    }
                    $array['res']=round($res/count($noteprofil), 2);
                }
                return $this->render('default/profil/moi.html.twig',array('array'=>$array,'user'=>$user));
            
            }
            return $this->render('default/profil/modifieMdp.html.twig',array( 'array' => $array,'form' => $form->createView()));
        }
        else{
            return $this->redirectToRoute("homepage");
        }
    }
    //completer un profil
    /**
     * @Route("/profil/completer", name="/profil/completer")
     */

    public function completerAction(Request $request)
    {
        $this->init($request);
        if($this->session->has('id')){
            $array = array();
            $dir="uploads/images";
            $array['login']=$this->getLogin();
            $user=$this->repository->findOneByLogin($this->getLogin());
            $form = $this->createFormBuilder($user)
            ->add('nom', TextType::class, array('required'   => false))
            ->add('prenom', TextType::class, array('required'   => false))
            ->add('email', EmailType::class, array('required'   => false,))
            ->add('tel', TextType::class, array('required'   => false,))
            ->add('description', TextareaType::class, array('required'=> false,'attr' => array('cols' => '70', 'rows' => '10')))
            ->add('save',SubmitType::class, array('label' => 'Validez'))
            ->getForm();
            //test si formulaire rempli
            $form->handleRequest($request);
            if($form->isSubmitted() && $form->isValid()){
                $this->em = $this->getDoctrine()->getManager();
                $this->em->persist($user);
                $this->em->flush();
                return $this->redirectToRoute('/profil/moi');
            }
            return $this->render('default/profil/completer.html.twig',array( 'array' => $array,'form' => $form->createView()));
        }
        else{
            return $this->redirectToRoute("homepage");
        }
    }

    /**
     * @Route("/profil/mescom", name="/profil/mescom")
     */
    public function mescomAction(Request $request)
    {
        $this->init($request);
        if($this->session->has('id')){
            $array = array();
            $array['login']=$this->getLogin();
            $user=$this->repository->findOneByLogin($this->getLogin());
            $res=0;
            $noteprofil=$this->repEval->findBy(['idDestinataire' =>$user->getId()]); 
            if (!$noteprofil) {
                $array['res']='Vous n\'avez pas encore de Commentaire';
                return $this->render('default/profil/mescommentaires.html.twig',array( 'array' => $array));
            }
            return $this->render('default/profil/mescommentaires.html.twig',array( 'array' => $array,'user'=>$user,'noteprofil'=>$noteprofil));
        }else{
            return $this->redirectToRoute("homepage");
        }
    }

    /**
     * @Route("/profil/photo", name="/profil/photo")
     */
    public function photoAction(Request $request)
    {
        $this->init($request);
        if($this->session->has('id')){
            $array = array();
            $dir="uploads/images";
            $array['login']=$this->getLogin();
            $user=$this->repository->findOneByLogin($this->getLogin());
            $form = $this->createFormBuilder($user)
            ->add('photo', FileType::class, array('required'=> false,'label' => ' (JPEG file)','data_class' => null,))
            ->add('save',SubmitType::class, array('label' => 'Validez'))
            ->getForm();
            $form->handleRequest($request);
            if($form->isSubmitted() && $form->isValid()){
                $file=$user->getPhoto();
                $fileName=$this->generateUniqueFileName().'.'.$file->guessExtension();
                $file->move($dir,$fileName);
                $photo="uploads/images/".$fileName;
                $user->setPhoto($photo);
                $this->em = $this->getDoctrine()->getManager();
                $this->em->persist($user);
                $this->em->flush();
                return $this->redirectToRoute('/profil/moi');
            }
            return $this->render('default/profil/photo.html.twig',array( 'array' => $array,'user'=>$user,'form'=>$form->createView()));
        }else{
            return $this->redirectToRoute("homepage");
        }
    }

    /**
     * @Route("/*", name="/*")
     */
    public function autreAction(Request $request)
    {
        $this->init($request);
        if($this->session->has('id')){
            return $this->redirectToRoute("/bienvenue");
        }else{
            return $this->redirectToRoute("homepage");
        }
    }

}
