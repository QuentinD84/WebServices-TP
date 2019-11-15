<?php

namespace App\Controller;

use App\Entity\User;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    /**
     * @SWG\Post(
     *     path="/register",
     *     summary="Permet l'enregistrement des utilisateurs.",
     *     description="Cette route permet l'ajout d'utilisateurs, elle retourne l'id du compte généré à partir du code de la banque (défini dans le .env) et de l'id unique",
     *      @SWG\Parameter(
     *         name="name",
     *         in="body",
     *         type="string",
     *         required=true,
     *         description="Customer's name",
     *         @SWG\Schema(type="string", example="name=toto")
     *      ),
     *      @SWG\Parameter(
     *         name="surname",
     *         in="body",
     *         type="string",
     *         required=true,
     *         description="Customer's surname",
     *         @SWG\Schema(type="string", example="name=toto")
     *      ),
     *      @SWG\Parameter(
     *         name="birthday",
     *         in="body",
     *         type="string",
     *         required=true,
     *         description="Customer's birthday with format Y-m-d",
     *         @SWG\Schema(type="string", example="name=1997-08-20")
     *      ),
     *      @SWG\Parameter(
     *         name="password",
     *         in="body",
     *         type="string",
     *         required=true,
     *         description="Customer's password",
     *         @SWG\Schema(type="string", example="name=toto")
     *      ),
     *      @SWG\Parameter(
     *         name="address",
     *         in="body",
     *         type="string",
     *         required=true,
     *         description="Customer's address",
     *         @SWG\Schema(type="string", example="name=toto")
     *      ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returns the account Id generated for the user",
     *         @SWG\Schema(type="string", example="111110000001A")
     *      )
     * )
     * @param Request $request
     * @param UserPasswordEncoderInterface $encoder
     * @param ValidatorInterface $validator
     * @return Response
     */
    public function register(Request $request, UserPasswordEncoderInterface $encoder, ValidatorInterface $validator)
    {
        $dotenv = new Dotenv();
        $dotenv->loadEnv(__DIR__."/../../.env");
        $em = $this->getDoctrine()->getManager();
        $data = json_decode($request->getContent(), true);
        $name = $data["name"];
        $surname = $data["surname"];
        $gender = $data["gender"];
        $address = $data["address"];
        $password = $this->generateRandomString();
        $upperLimit = $data["upperLimit"];
        $birthday = \DateTime::createFromFormat('Y-m-d', $data["birthday"]);
        $user = new User();
        $user->setName($name);
        $user->setSurname($surname);
        $user->setGender($gender);
        $user->setAddress($address);
        $user->setBalance(0);
        $user->setUpperLimit($upperLimit);
        $user->setPassword($encoder->encodePassword($user, $password));
        $user->setBirthday($birthday);
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorsString = (string) $errors;
            return new Response($errorsString, 400);
        }
        $em->persist($user);
        $em->flush();
        $user->setAccountId($_ENV["BANKID"].substr($_ENV["IDGENERATOR"], 0, 7-strlen((string)$user->getId())).$user->getId()."A");
        $em->persist($user);
        $em->flush();
        return new Response(json_encode(array("accountId" => $user->getAccountId(), "pinCode" => $password)), 200, ['Content-Type' => 'application/json']);
    }

    function generateRandomString($length = 6) {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    /**
     * @SWG\Post(
     *     path="/login",
     *     summary="Permet la connexion des utilisateurs.",
     *     description="Cette route renvoie le token JWT en échange d'identifiants de connexion valides",
     *      @SWG\Parameter(
     *         name="username",
     *         in="body",
     *         type="string",
     *         required=true,
     *         description="Customer's account id",
     *         @SWG\Schema(type="string", example="toto")
     *      ),
     *      @SWG\Parameter(
     *         name="password",
     *         in="body",
     *         type="string",
     *         required=true,
     *         description="Customer's password",
     *         @SWG\Schema(type="string", example="toto")
     *      ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returns the JWT token",
     *         @SWG\Schema(type="string", example="JWT Token")
     *      ),
     *     @SWG\Response(
     *         response=401,
     *         description="If wrong credentials, returns Unauthorized",
     *         @SWG\Schema(type="string", example="Unauthorized")
     *      )
     * )
     */
    public function login() {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        if ($user instanceof User) {
            $user->setIsLoggedIn(1);
            $em->persist($user);
            $em->flush();
        }
    }

    /**
     * @SWG\Post(
     *     path="/login",
     *     summary="Permet la déconnexion des utilisateurs.",
     *     description="Cette route utilise l'ID de l'utilisateur présent dans le token JWT pour le déconnecter et rentre le token invalide.",
     *     @SWG\Response(
     *         response=200,
     *         description="Returns a message for confirmation.",
     *         @SWG\Schema(type="string", example="User logged out")
     *      )
     * )
     */
    public function logout() {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        if ($user instanceof User) {
            $user->setIsLoggedIn(0);
            $em->persist($user);
            $em->flush();
        }
        return new Response("User logged out");
    }
}

