<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Form\UploadFileType;
use App\Security\LoginAuthenticator;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register", name="app_register")
     */
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, LoginAuthenticator $authenticator, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $formFile = $this->createForm(UploadFileType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
            $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();
            // do anything else you need here, like send an email

            return $userAuthenticator->authenticateUser(
                $user,
                $authenticator,
                $request
            );
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'formFile'=>$formFile->createView()
        ]);
    }

    /**
     * @Route("/register-from-file", name="app_file_register")
     */
    public function loadUsers(
     Request $request, 
     UserPasswordHasherInterface $userPasswordHasher, 
     EntityManagerInterface $entityManager,
     FileUploader $fileUploader): Response{
        $formFile = $this->createForm(UploadFileType::class);
        $formFile->handleRequest($request);
        if ($formFile->isSubmitted()) {           
            $usersFile = $formFile->get('users')->getData();
            if ($usersFile) {
                $usersFileName = $fileUploader->upload($usersFile);
                $row = 0;
                if (($handle = fopen($this->getParameter("upload_directory")."/".$usersFileName, "r")) !== FALSE) {
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $num = count($data);
                        $row++;
                        if($row==1) continue; 
                        $user = new User();
                        $user->setEmail($data[0]);
                        $user->setRoles([$data[1]]);
                        $user->setPassword(
                            $userPasswordHasher->hashPassword(
                                    $user,
                                    $data[2]
                                )
                            );
                        $entityManager->persist($user);
                        
                    }
                    fclose($handle);
                    $entityManager->flush();
                    return $this->redirectToRoute("app_login");
                }
            }
            
        }

        return $this->redirectToRoute("app_register");

    }

}
