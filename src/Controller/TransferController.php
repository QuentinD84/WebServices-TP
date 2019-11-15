<?php

namespace App\Controller;

use App\Entity\User;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class TransferController extends AbstractController
{

    /**
     * @SWG\Post(
     *     path="/api/customer/manageBalance",
     *     summary="Permet de retirer ou déposer de l'argent",
     *     description="Le seule paramètre à envoyer est le montant que l'on souhaite retirer ou déposer, l'identifiant de l'utilisateur est récupéré dans le token JWT",
     *      @SWG\Parameter(
     *         name="amount",
     *         in="body",
     *         type="integer",
     *         required=true,
     *         description="Withdrawal or deposit amount",
     *         @SWG\Schema(type="integer", example="amount=10")
     *      ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returns the customer's balance after the deposit or withdrawal.",
     *         @SWG\Schema(type="string", example="Your balance has been updated : 20€")
     *      ),
     *     @SWG\Response(
     *         response=401,
     *         description="Returns an error",
     *         @SWG\Schema(type="string", example="You can't have a negative balance.")
     *      )
     * )
     * @param Request $request
     * @return Response
     */
    public function manageBalance(Request $request)
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $em = $this->getDoctrine()->getManager();
        if ($user instanceof User) {
            if (!$user->getIsLoggedIn()) {
                return new Response("Expired JWT Token.", 401);
            }
            if (($user->getBalance()+$data["amount"]) > $user->getUpperLimit()) {
                return new Response("You can't exceed your upper limit.", 401);
            }
            else if (($user->getBalance()+$data["amount"]) < 0) {
                return new Response("You can't have a negative balance.", 401);
            }
            $user->setBalance($user->getBalance()+$data["amount"]);
            $em->persist($user);
            $em->flush();
        }
        return new Response(sprintf("Your balance has been updated : %s€", $user->getBalance()));
    }

    /**
     * @SWG\Post(
     *     path="/api/customer/transferMoney",
     *     summary="Permet d'effectuer des virements internes ou externes",
     *     description="Si le compte destinataire est dans la même banque, le virement est effectué immédiatement. Autrement, le virement est envoyé à la seconde banque qui se réserve le droit de le refuser. Les 5 premiers chiffres de l'id de compte permettent de définir si le compte appartient à la banque courante. Le cas contraire, deux variables présentes dans le fichier d'environnement permettent de faire le mapping entre l'ID de la banque et le port de communication avec son API. La banque envoie donc une nouvelle requête en s'identifiant avec son Bankid (pour l'occasion, égal à son ID) et en renvoyant le body initial. La banque bénéficiaire fait alors toutes les vérifications nécessaires (vérification du Bankid, du solde inférieur au plafond, de l'existence de l'ID de compte.). Elle renvoie en sutie à la banque émettrice le code de succès ou d'erreur.",
     *      @SWG\Parameter(
     *         name="amount",
     *         in="body",
     *         type="integer",
     *         required=true,
     *         description="Amount to transfer",
     *         @SWG\Schema(type="integer", example="amount=10")
     *      ),
     *     @SWG\Parameter(
     *         name="accountId",
     *         in="body",
     *         type="string",
     *         required=true,
     *         description="Benneficiary's account id",
     *         @SWG\Schema(type="integer", example="accountId=111110000000000A")
     *      ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returns the customer's balance after the transfer",
     *         @SWG\Schema(type="string", example="Your balance has been updated : 20€")
     *      ),
     *     @SWG\Response(
     *         response=401,
     *         description="Returns an error",
     *         @SWG\Schema(type="string", example="The beneficiary's bank has refused the transaction.")
     *      )
     * )
     * @param Request $request
     * @return Response
     */
    public function transferMoney(Request $request)
    {

        $dotenv = new Dotenv();
        $dotenv->load(__DIR__."/../../.env");
        $data = json_decode($request->getContent(), true);
        $em = $this->getDoctrine()->getManager();
        $toUser = $em->getRepository("App:User")->findOneBy(array("accountId" => $data["accountId"]));
        $user = $this->getUser();
        if ($user instanceof User && $toUser instanceof User) {
            if (!$user->getIsLoggedIn()) {
                return new Response("Expired JWT Token.", 401);
            }
            if (($toUser->getBalance()+$data["amount"]) > $toUser->getUpperLimit()) {
                return new Response("The beneficiary has reached his upper limit.", 401);
            }
            else if (($user->getBalance()-$data["amount"]) < 0) {
                return new Response("You can't have a negative balance.", 401);
            }
            $user->setBalance($user->getBalance()-$data["amount"]);
            $toUser->setBalance($toUser->getBalance()+$data["amount"]);
            $em->persist($user);
            $em->flush();
        }
        else if ($user instanceof User) {
            $banksPort = $this->getBanksPort($data["accountId"]);

            if ($banksPort) {
                if (($user->getBalance()-$data["amount"]) < 0) {
                    return new Response("You can't have a negative balance.", 401);
                }
                $httpClient = HttpClient::create();
                try {
                    $response = $httpClient->request('POST', "http://127.0.0.1:8001/api/bank/transferMoney", [
                        'body' => $request->getContent(),
                        'headers' => [
                            'Content-Type' => "application/json",
                            'Authorization' => "Bankid ".$_ENV["BANKID"]
                        ]
                    ]);
                    if ($response->getStatusCode() === 200) {
                        $user->setBalance($user->getBalance()-$data["amount"]);
                    }
                    else {
                        return new Response("The beneficiary's bank has refused the transaction", 403);
                    }
                } catch (TransportExceptionInterface $e) {
                    return new Response("The beneficiary's bank couldn't be contacted.", 504);
                }
            } else {
                return new Response("The beneficiary's bank doesn't exists", 403);
            }
        }
        return new Response(sprintf("The transfer has been processed and your balance has been updated : %s€", $user->getBalance()));
    }

    private function getBanksPort($account) {
        $bankId = substr($account, 0, 5);

        $dotenv = new Dotenv();
        $dotenv->load(__DIR__."/../../.env");
        $banks = explode(".",$_ENV["OTHERBANKSID"]);
        for ($i = 0 ; $i < sizeof($banks) ; $i++) {
            if ($banks[$i] === $bankId) {
                return explode(".", $_ENV["OTHERBANKSPORTS"])[$i];
            }
        }
        return null;
    }

    private function validateBank($bankId) {
        $id = explode(" ", $bankId)[1];
        $banks = explode(".",$_ENV["OTHERBANKSID"]);
        for ($i = 0 ; $i < sizeof($banks) ; $i++) {
            if ($banks[$i] === (string)$id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @SWG\Post(
     *     path="/api/bank/transferMoney",
     *     summary="Permet à la banque de recevoir des virements externes",
     *     description="Après vérification de l'ID de la banque émétrice, on vérifie que le plafond n'est pas dépassé avant d'accepter le virement et de renvoyer un code de succès.",
     *      @SWG\Parameter(
     *         name="amount",
     *         in="body",
     *         type="integer",
     *         required=true,
     *         description="Amount to transfer",
     *         @SWG\Schema(type="integer", example="amount=10")
     *      ),
     *     @SWG\Parameter(
     *         name="accountId",
     *         in="body",
     *         type="string",
     *         required=true,
     *         description="Benneficiary's account id",
     *         @SWG\Schema(type="integer", example="accountId=111110000000000A")
     *      ),
     *     @SWG\Parameter(
     *         name="Authorization",
     *         in="header",
     *         type="string",
     *         required=true,
     *         description="Authorization : Bankid XXXXX",
     *         @SWG\Schema(type="integer", example="Authorization : Bankid 11111")
     *      ),
     *     @SWG\Response(
     *         response=200,
     *         description="Returns a success message.",
     *         @SWG\Schema(type="string", example="The beneficiary's account has been updated.")
     *      ),
     *     @SWG\Response(
     *         response=401,
     *         description="Returns an error",
     *         @SWG\Schema(type="string", example="The beneficiary has reached his upper limit.")
     *      )
     * )
     * @param Request $request
     * @return Response
     */
    public function transferMoneyBank(Request $request) {
        $dotenv = new Dotenv();
        $dotenv->load(__DIR__."/../../.env");
        $data = json_decode($request->getContent(), true);
        $em = $this->getDoctrine()->getManager();

        if (!$this->validateBank($request->headers->get('Authorization'))) {
            return new Response("Unknown source bank.", 403);
        }
        $user = $em->getRepository("App:User")->findOneBy(array("accountId" => $data["accountId"]));
        if (!$user) {
            return new Response("Unknown account ID.", 403);
        }
        if ($user->getBalance()+$data["amount"] > $user->getUpperLimit()) {
            return new Response("The beneficiary has reached his upper limit.", 401);
        }
        $user->setBalance($user->getBalance()+$data["amount"]);
        $em->persist($user);
        $em->flush();
        return new Response("The beneficiary's account has been updated.");
    }
}