# Bienvenue sur le rendu de Quentin Despeisse !

Bonjour, afin d'assurer le fonctionnement de mon projet sur votre PC, voici quelques petits conseils à suivre :


# Pré-Requis

Tout d'abord, vous devez disposer d'un gestionnaire de bases de données en route allumé, de préférence **MySQL**.
Vous devez également disposer de OpenSSL pour générer les certificats mais j'imagine que c'est déjà le cas. ;)

## Clonage du projet

Vous devez cloner deux fois le projet pour simuler la présence de deux banques.
Pour faciliter la compréhension nous les appellerons **bank1** et **bank2**.
Rendez vous dans le projet **bank2** et renommez le fichier **.env2** en **.env** ce qui aura pour action d'écraser le **.env** actuel.
Ouvrez ce fichier, vous devez alors modifier la chaine de connexion à la base de donnée dans la variable d'environnement **DATABASE_URL**.
ATTENTION : Veillez à laisser le nom de la base de données tel quel (bank2 en l'occurence).

Vous pouvez procéder à la mise à jour du **DATABASE_URL** dans **bank1** également.

## Création des bases de données

Pour chacun des deux projets, jouer les lignes suivantes :

    $ composer update
    $ php bin/console doctrine:database:create
    $ php bin/console doctrine:schema:update --force

Vous venez de créer la base de données et de générer le schéma.

## Génération des certificats JWT

Pour chacun des deux projets, jouer les lignes suivantes :

    $ mkdir -p config/jwt
    $ openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
    $ openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

## Lancer les projets

Vous pouvez désormais lancer chacun des deux projets.
ATTENTION : Pour assurer la bonne attribution des ID de banque en fonction des ports attribués Symfony, veillez à lancer **bank1** avant **bank2**.
Pour lancer un projet, se placer dans son dossier racine et exécuter :

    $ php bin/console server:run

## Quelques informations utiles

Avant d'utiliser les routes, je vous conseille d'accéder au Swagger qui est généré automatiquement par le Bundle Nelmio grâce aux annotations PHP.
Voici le lien pour accéder au Swagger une fois **bank1** démarré :
[http://127.0.0.1:8000/doc](http://127.0.0.1:8000/doc)
Ce Swagger ne permet pas d'envoyer les requêtes par formulaire mais il donne toutes le sinformations nécessaires à l'appel des requêtes par Postman ou équivalent.