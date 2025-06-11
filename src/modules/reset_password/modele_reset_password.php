<?php

require_once __DIR__ . '/../../connexion.php';
require_once __DIR__ . '/../../../vendor/autoload.php'; // Inclure PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ModeleResetPassword extends Connexion
{
    public function __construct() {}

    /**
     * Envoie un email via le SMTP de Mailtrap.
     *
     * @param string $to       L'adresse email du destinataire.
     * @param string $subject  L'objet de l'email.
     * @param string $body     Le contenu HTML de l'email.
     * @return bool            Retourne true si l'email a été envoyé, false sinon.
     */
    private function sendMail($to, $subject, $body)
    {
        $mail = new PHPMailer(true);
        try {
            // Configuration du serveur SMTP Mailtrap
            $mail->isSMTP();
            $mail->Host = 'sandbox.smtp.mailtrap.io';
            $mail->SMTPAuth = true;
            $mail->Port = 2525;
            $mail->Username = '787465f2de2697'; // Votre identifiant Mailtrap
            $mail->Password = '3533f50af7726d'; // Votre mot de passe Mailtrap

            // Expéditeur et destinataire
            $mail->setFrom('a01e94ac68-997602+1@inbox.mailtrap.io', 'EtudiAppart'); // Adresse et nom de l'expéditeur
            $mail->addAddress($to); // Adresse du destinataire

            // Contenu de l'email
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            // Envoyer
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Erreur d'envoi d'email : " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Génère un token de réinitialisation et envoie un email.
     *
     * @return bool Retourne true si tout s'est bien passé, false sinon.
     */
    public function sendPasswordReset()
    {
        if (isset($_POST['submit']) && isset($_POST['email'])) {
            $email = $_POST['email'];

            // Validation de l'email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo "Adresse email invalide.";
                return false;
            }

            // Générer un token sécurisé
            $strong = true;
            $token = bin2hex(openssl_random_pseudo_bytes(16, $strong));
            $token_hashed = hash("sha256", $token);
            $expiry = date("Y-m-d H:i:s", time() + 60 * 10);

            try {
                // Vérifier si l'email existe
                $checkSql = Connexion::getBdd()->prepare('SELECT COUNT(*) FROM User WHERE email = :email');
                $checkSql->bindParam(':email', $email, PDO::PARAM_STR);
                $checkSql->execute();

                if ($checkSql->fetchColumn() == 0) {
                    echo "Aucun utilisateur trouvé avec cet email.";
                    return false;
                }

                // Mettre à jour la base de données
                $sql = Connexion::getBdd()->prepare('
                    UPDATE User 
                    SET reset_token_hash = :reset_token_hash, reset_token_expires_at = :reset_token_expires_at 
                    WHERE email = :email
                ');
                $sql->bindParam(':email', $email, PDO::PARAM_STR);
                $sql->bindParam(':reset_token_hash', $token_hashed, PDO::PARAM_STR);
                $sql->bindParam(':reset_token_expires_at', $expiry, PDO::PARAM_STR);

                if ($sql->execute()) {
                    // Préparer l'email
                    $subject = "Réinitialisation de votre mot de passe EtudiAppart";
                    $body = "
                        <p>Bonjour,</p>
                        <p>Veuillez cliquer sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
                        <p><a href='http://localhost/APP-Projet-commun-Serre/index.php?module=resetPassword&action=resetPassword&token=$token'>
                        Réinitialiser mon mot de passe</a></p>
                        <p>Ce lien expirera dans 10 minutes.</p>
                        <p>Cordialement,<br>L'équipe EtudiAppart</p>
                    ";

                    // Envoyer l'email
                    if ($this->sendMail($email, $subject, $body)) {
                        echo "Un email de réinitialisation a été envoyé à votre adresse.";
                        return true;
                    } else {
                        echo "Une erreur s'est produite lors de l'envoi de l'email.";
                        return false;
                    }
                } else {
                    echo "Échec de la mise à jour.";
                    return false;
                }
            } catch (PDOException $e) {
                echo "Erreur : " . $e->getMessage();
                return false;
            }
        }
        return false;
    }

    public function resetPassword()
    {
        $token = $_GET["token"] ?? null;

        if (!$token) {
            return ['success' => false, 'message' => 'Token manquant'];
        }

        $token_hashed = hash("sha256", $token);

        $checkSql = Connexion::getBdd()->prepare('SELECT * FROM User WHERE reset_token_hash = :reset_token_hash');
        $checkSql->bindParam(':reset_token_hash', $token_hashed, PDO::PARAM_STR);
        $checkSql->execute();

        $user = $checkSql->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'Token not found'];
        }

        if (strtotime($user['reset_token_expires_at']) <= time()) {
            return ['success' => false, 'message' => 'Token has expired'];
        }

        return ['success' => true, 'token' => $token];
    }

    public function verifierEtModifierMotDePasse()
    {
        // Vérifier la présence des paramètres nécessaires
        if (!isset($_GET["token"]) || !isset($_POST["password"]) || !isset($_POST["confirm_password"])) {
            echo "Paramètres manquants.";
            return;
        }

        $token = $_GET["token"];
        $password = $_POST["password"];
        $confirmPassword = $_POST["confirm_password"];

        // Vérification si les mots de passe correspondent
        if ($password !== $confirmPassword) {
            echo "Les mots de passe ne correspondent pas.";
            return;
        }

        // Hacher le nouveau mot de passe
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $token_hashed = hash("sha256", $token);

        try {
            // Vérifier si le token est valide
            $checkSql = Connexion::getBdd()->prepare('
            SELECT email 
            FROM User 
            WHERE reset_token_hash = :reset_token_hash 
              AND reset_token_expires_at > NOW()
        ');
            $checkSql->bindParam(':reset_token_hash', $token_hashed, PDO::PARAM_STR);
            $checkSql->execute();

            $user = $checkSql->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                echo "Token invalide ou expiré.";
                return;
            }

            $email = $user['email'];

            // Mettre à jour le mot de passe
            $updateSql = Connexion::getBdd()->prepare('
            UPDATE User 
            SET password = :password, reset_token_hash = NULL, reset_token_expires_at = NULL 
            WHERE email = :email
        ');
            $updateSql->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $updateSql->bindParam(':email', $email, PDO::PARAM_STR);

            if ($updateSql->execute()) {
                echo "Modification réussie.";
            } else {
                echo "Modification échouée.";
            }
        } catch (PDOException $e) {
            echo "Erreur : " . $e->getMessage();
        }
    }
}