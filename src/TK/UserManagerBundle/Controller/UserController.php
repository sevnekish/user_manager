<?php

namespace TK\UserManagerBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use TK\UserManagerBundle\Entity\User;
use TK\UserManagerBundle\Form\UserType;

use TK\UserManagerBundle\Entity\UserAddress;
use Doctrine\Common\Collections\ArrayCollection;

use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

/**
 * User controller.
 *
 * @Route("/user")
 */
class UserController extends Controller
{

  /**
   * Lists all User entities.
   *
   * @Route("/", name="user")
   * @Method("GET")
   * @Template()
   */
  public function indexAction(Request $request)
  {
    $em = $this->getDoctrine()->getManager();

    // $entities = $em->getRepository('TKUserManagerBundle:User')->findAll();

    // $dql   = "SELECT user
    //           FROM TKUserManagerBundle:User user 
    //           ORDER BY user.id DESC";
    $dql   = "SELECT user
              FROM TKUserManagerBundle:User user
              JOIN user.userRole role
              ORDER BY user.id DESC";
    
    // exit(\Doctrine\Common\Util\Debug::dump($queryBuilder));
    $query = $em->createQuery($dql);

    $paginator  = $this->get('knp_paginator');
    $pagination = $paginator->paginate(
        $query,
        $request->query->getInt('page', 1)/*page number*/,
        10/*limit per page*/
    );

    $deleteForms = array();

    foreach ($pagination->getItems() as $user) {
      $deleteForm = $this->createDeleteForm($user->getId());
      $deleteForms[] = $deleteForm->createView();
    }
    return array(
      'pagination'  => $pagination,
      'deleteForms' => $deleteForms,
    );
  }
  /**
   * Creates a new User entity.
   *
   * @Route("/", name="user_create")
   * @Method("POST")
   * @Template("TKUserManagerBundle:User:new.html.twig")
   */
  public function createAction(Request $request)
  {
    $entity = new User();

    $form = $this->createCreateForm($entity);

    $form->handleRequest($request);

    if ($form->isValid()) {
      $em = $this->getDoctrine()->getManager();
      $em->persist($entity);
      $em->flush();

      $this->addFlash(
                  'messages', ['success' => ['User has been added successfully!']]
      );

      return $this->redirect($this->generateUrl('home'));
    }

    return array(
      'entity' => $entity,
      'form'   => $form->createView(),
    );
  }

  /**
   * Displays a form to create a new User entity.
   *
   * @Route("/new", name="user_new")
   * @Method("GET")
   * @Template()
   */
  public function newAction()
  {
    $entity = new User();

    $address1 = new UserAddress();
    $entity->getUserAddresses()->add($address1);

    $form   = $this->createCreateForm($entity);

    return array(
      'entity' => $entity,
      'form'   => $form->createView(),
    );
  }

  /**
   * Finds and displays a User entity.
   *
   * @Route("/{id}", name="user_show")
   * @Method("GET")
   * @Template()
   */
  public function showAction($id)
  {
    $em = $this->getDoctrine()->getManager();

    $entity = $em->getRepository('TKUserManagerBundle:User')->find($id);

    if (!$entity) {
      $this->addFlash(
                  'messages', ['danger' => ['Unable to find User with id = ' . $id]]
      );
      return $this->redirect($this->generateUrl('user_show'));
    }

    $deleteForm = $this->createDeleteForm($id);

    return array(
      'entity'      => $entity,
      'delete_form' => $deleteForm->createView(),
    );
  }

  /**
   * Displays a form to edit an existing User entity.
   *
   * @Route("/{id}/edit", name="user_edit")
   * @Method("GET")
   * @Template()
   */
  public function editAction($id)
  {
    $em = $this->getDoctrine()->getManager();

    $entity = $em->getRepository('TKUserManagerBundle:User')->find($id);

    if (!$entity) {
      $this->addFlash(
                  'messages', ['danger' => ['Unable to find User with id = ' . $id]]
      );
      return $this->redirect($this->generateUrl('user_show'));
    }

    $editForm   = $this->createEditForm($entity);
    $deleteForm = $this->createDeleteForm($id);

    return array(
      'entity'      => $entity,
      'form'        => $editForm->createView(),
      'delete_form' => $deleteForm->createView(),
    );
  }


  /**
   * Edits an existing User entity.
   *
   * @Route("/{id}", name="user_update")
   * @Method("PUT")
   * @Template("TKUserManagerBundle:User:edit.html.twig")
   */
  public function updateAction(Request $request, $id)
  {
    $em = $this->getDoctrine()->getManager();

    $entity = $em->getRepository('TKUserManagerBundle:User')->find($id);

    if (!$entity) {
      $this->addFlash(
                  'messages', ['danger' => ['Unable to find User with id = ' . $id]]
      );
      return $this->redirect($this->generateUrl('user_show'));
    }

    $originalUserAddresses = new ArrayCollection();
    $originalPassword = $entity->getPassword();

    foreach ($entity->getUserAddresses() as $userAdress) {
      $originalUserAddresses->add($userAdress);
    }

    $deleteForm = $this->createDeleteForm($id);
    $editForm = $this->createEditForm($entity);
    $editForm->handleRequest($request);

    if ($editForm->isValid()) {

      $password = $editForm->get('password')->getData();
      if (empty($password)) {
          $entity->setPassword($originalPassword);
      }
      // filter $originalUserAddresses to contain addresses no longer present
      foreach ($originalUserAddresses as $originalUserAddress) {
        if($entity->getUserAddresses()->contains($originalUserAddress) === false) {
          $entity->removeUserAddress($originalUserAddress);
          $originalUserAddress->setUser(null);
          $em->remove($originalUserAddress);
        }
      }

      $em->persist($entity);
      $em->flush();
      $this->addFlash(
                  'messages', ['success' => ['User has been updated successfully!']]
      );

      return $this->redirect($this->generateUrl('user_show', array('id' => $entity->getId())));
    }

    return array(
      'entity'      => $entity,
      'form'   => $editForm->createView(),
      'delete_form' => $deleteForm->createView(),
    );
  }
  /**
   * Deletes a User entity.
   *
   * @Route("/{id}", name="user_delete")
   * @Method("DELETE")
   */
  public function deleteAction(Request $request, $id)
  {

    $form = $this->createDeleteForm($id);
    $form->handleRequest($request);
    $securityContext = $this->get('security.context');
    // destroy session before remove entity
    if($securityContext->isGranted('IS_AUTHENTICATED_FULLY')) {
        $token = $securityContext->getToken();
        $current_user = $token->getUser();

        if ($id == $current_user->getId()) {
          
          $securityContext->setToken(null);
          $request->getSession()->invalidate();
          // echo '<pre>';
          // exit(\Doctrine\Common\Util\Debug::dump($current_user));
        }
    }
    // echo 'stop';exit;

    if ($form->isValid()) {
      $em = $this->getDoctrine()->getManager();
      $entity = $em->getRepository('TKUserManagerBundle:User')->find($id);

      if (!$entity) {
        $this->addFlash(
                    'messages', ['danger' => ['Unable to find User with id = ' . $id]]
        );
        return $this->redirect($this->generateUrl('user_show'));
      }

      $em->remove($entity);
      $em->flush();
    }

    return $this->redirect($this->generateUrl('user'));
  }

  /**
   * Creates a form to create a User entity.
   *
   * @param User $entity The entity
   *
   * @return \Symfony\Component\Form\Form The form
   */
  private function createCreateForm(User $entity)
  {
    $form = $this->createForm(new UserType(), $entity, array(
      'validation_groups' => array('create'),
      'action' => $this->generateUrl('user_create'),
      'method' => 'POST',
      'attr'   => array(
                        'id' =>'createForm'
      ),
    ));
    $form->add('password', 'repeated', array(
              'validation_groups' => array('create'),
              'type' => 'password',
              'invalid_message' => 'The password fields must match.',
              'options' => array('attr' => array('class' => 'password-field')),
              'required' => true,
              'first_options'  => array('label' => 'Password'),
              'second_options' => array('label' => 'Repeat Password')
    ));

    $form->add('submit', 'submit', array('label' => 'Create'));

    return $form;
  }

  /**
  * Creates a form to edit a User entity.
  *
  * @param User $entity The entity
  *
  * @return \Symfony\Component\Form\Form The form
  */
  private function createEditForm(User $entity)
  {
    $form = $this->createForm(new UserType(), $entity, array(
      'action' => $this->generateUrl('user_update', array('id' => $entity->getId())),
      'method' => 'PUT',
      'attr'   => array(
                        'id' =>'editForm'
      ),
    ));

    $form->add('password', 'repeated', array(
              'validation_groups' => array('edit'),
              'type' => 'password',
              'invalid_message' => 'The password fields must match.',
              'options' => array('attr' => array('class' => 'password-field')),
              'required' => false,
              'first_options'  => array('label' => 'Password'),
              'second_options' => array('label' => 'Repeat Password')
    ));

    $form->add('submit', 'submit', array('label' => 'Update'));

    return $form;
  }

  /**
   * Creates a form to delete a User entity by id.
   *
   * @param mixed $id The entity id
   *
   * @return \Symfony\Component\Form\Form The form
   */
  private function createDeleteForm($id)
  {
    return $this->createFormBuilder()
      ->setAction($this->generateUrl('user_delete', array('id' => $id)))
      ->setMethod('DELETE')
      ->add('submit', 'submit', array('label' => 'Delete'))
      ->getForm()
    ;
  }
}
