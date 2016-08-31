<?php
/*
 *	A shell utility to configure the plugin from the command line.
 *
 *	@author Véronique Bellamy <v@vero.moe>
 *	@since 0.1-dev
 */
namespace App\Shell;
use Cake\Console\Shell;

class MongoShell extends Shell
{
    public function main() {
        $this->out('CakePHP MongoDB shell plugin. To initialize, type "bin/cake mongo init" (without the quotation marks) to set this up.');
    }
    public function init() { // TODO: Add the option to define variables when calling the function.
	    $this->out('Welcome to the MongoDB CakePHP plugins shell initialization mode.');
	    $this->out("This plugin will write a configuration file to your config/folder, please wait one moment while we check to see if that file already exists...");
	    try {
		    Configure::load("mongodb", "default", false);
		    $this->abort("Configuration file mongodb.php exists. In an effort to avoid causing problems with data already written, this wizard will now shut down.");
	    } catch (Cake\Core\Exception $e) {
		    $this->out("Okay, so the configuration file does not exist. Let's try to create it...");
		    try {
			    Configure::write('Errata.source', 'MongoShell'); // Write where this config file came from.
			    Configure::dump('mongodb', 'default', ['Errata']);
			    $this->out("Configuration file created successfully.");
		    } catch (Cake\Core\Exception $e) {
			    $this->abort("Couldn't write configuration file. Here's the scoop from the error handler: \n\n" . $e);
		    }
	    }
	    $sshquestion = false; // Because they haven't answered it yet.
	    $sshhost = null;
	    $sshport = null;
	    $sshuser = null;
	    $sshpass = null;
	    $sshpubkey = null; // The path to the public key.
	    $sshpublickey = new File(); // The file contents of the public key.
	    $sshprikey = null;
	    $host = null;
	    $db = null;
	    while (!$sshquestion) {
		    $ssh = $this->in("To get started, do you intend to use a SSH tunnel to connect to your MongoDB installation? (y/n)");
		    if ($ssh == "y" | $ssh == "n") {
		    	$sshquestion = true;
		    } else {
			    $this->out("I'm sorry, I didn't quite get that.");
		    }
	    }
	    if ($ssh == "y") {
		    $this->out("Alright, very good. To get started, I'm going to need a few pieces of information from you.");
		    $hostquestion = false;
		    while (!$hostquestion) {
			    $sshhost = $this->in("So, what is the host name that you wish to create a SSH tunnel with?");
			    if (!$sshhost) {
				    $this->out("I'm afraid I didn't get that. Let's try this again.");
			    } elseif (!$this->isValidDomain($sshhost)) {
				    $this->out("That host name appears to be invalid.");
			    } else {
				    $confirm = $this->in("Great, so your SSH hostname is " . $sshhost . "? (y/n)");
				    if ($confirm == "y") {
					    $this->out("Awesome. Glad we established that.");
					    $hostquestion = true;
				    } elseif ($confirm == "n") {
					    $this->out("No? Very well, then.");
				    } else {
					    $this->out("Did you fall asleep at your keyboard? I didn't understand that.");
				    }
			    }
		    }
		    
		    $portquestion = false;
		    while (!$portquestion) {
			    $sshport = $this->("So, what port should we create the SSH tunnel to " . $sshhost . "on? (Hit enter for default, port 22)");
			    if ($sshport == "") {
				    $sshport = 22;
				    $this->out("Great, so we're going with port 22 on " . $sshhost . "! Fine choice.");
				    $portquestion = true;
			    } elseif (!intval($sshport)) {
				    $this->out("Whoopsie, looks like your fingers slipped! Port numbers need to be integers.");
			    } elseif (intval($sshport) > 65535) {
				    $this->out("Looks like you shot the moon, cowpoke. Per the IANA, port numbers are capped at 65535. Let's try this again.");
			    } elseif (intval($sshport) < 0) {
				    $this->out("People call me a negative person too, but port numbers can't be. Can we try that again?");
			    } else {
				    $this->out("Alrighty. Normally, people choose 22 but whatever floats your boat.");
				    $confirm = $this->in("Just to be clear, do you want to connect to " . $sshhost . " at port " . $sshport . " (y/n)?");
				    if ($confirm == "y") {
					    $this->out("Awesome.");
					    $portquestion = true;
				    } elseif ($confirm == "n") {
					    $this->out("No? Well, let's try this again.")
				    } else {
					    $this->out("I'm starting to worry about your health. Let's try this again.");
				    }
			    }
		    }
		    
		    $this->out("So, there are two ways to connect via SSH. Via a username and password combination or with a username and public/private key combo.");
		    $method = null;
		    $methodquestion = false;
		    while (!$methodquestion) {
			    $method = $this->in("Based on that, how do you plan to connect? (Use the word \"password\" or the word \"keys\" to indicate your choice.)");
			    if ($method == "password") {
				    $this->out("Okay, so you plan to use a password. Not my first choice, but whatever makes you happy.");
				    $methodquestion = true;
			    } elseif ($method == "keys") {
				    $this->out("Alrighty, so you're smart enough to use keys. Beautiful.");
				    $methodquestion = true;
			    } else {
				    $this->out("Okay, after this process, I'm recommending you see your physician. Or grammar teacher. Let's try this again.");
			    }
		    }
		    
		    $connection = ssh2_connect($sshhost, $sshport);
		    $this->out("So, we added a security feature to detect possible man-in-the-middle attacks based on hostkey mismatch.");
		    $this->out("In this script, we can set your hostkey in the config file to compare against the host key of any new connections.");
		    $this->out("It would appear that your hostkey is " . ssh2_fingerprint($connection, SSH2_FINGERPRINT_MD5));
		    $hostkeyquestion = $this->in("So, do you want to set your hostkey value to " . ssh2_fingerprint($connection, SSH2_FINGERPRINT_MD5) . "? (y/n)");
		    if ($hostkeyquestion == "y") {
			    $this->out("Good, we'll set it to that, then.");
		    } elseif ($hostkeyquestion == "n") {
			    $this->out("No? Well, you can set it in config/app.php when we're done here.");
		    } else {
			    $this->out("Whatever. I'll leave it up to you.");
		    }
		    
		    $userquestion = false;
			while (!$userquestion) {
				$sshuser = $this->in("Regardless of what method you selected, a username is still required. So, what's yours?");
				if ($sshuser == "") {
					$this->out("No. That seriously can't be empty.");
				} elseif ($sshuser == "root") {
					$this->out("Seriously? You're going to trust a third party plugin with your root connection? What if I asked for your credit card details?");
					$this->out("Whatever. Root it is.");
					$userquestion = true;
				} else {
					$this->out("Oh, like THAT's a real username.");
					$confirm = $this->in("Seriously, are we going to go with " . $sshuser . "@" . $sshhost . ":" . $sshport . "? (y/n)");
					if ($confirm == "y") {
						$this->out("That's an embarrassing URL, but it's your choice, stud.");
					} elseif ($confirm == "n") {
						$this->out("Thought not. Let's try it again.");
					} else {
						$this->out("While I'm dispatching the ambulances, let's try this again. (Note: I'm not really dispatching ambulances)");
					}
				}
			}
		    
		    if ($method == "password") {
			    $passquestion = false;
			    while (!$passquestion) {
				    $sshpass = $this->in("So, now that you've chosen a plain text password for your means of authentication, let's hear it!");
					if ($sshpass == "") {
						if ($sshuser == "root") {
							$confirm = $this->in("Tell me you're joking. Tell me you did NOT set up your server's root account with no password!?! (y/n)");
							if ($confirm == "y") {
								$this->out("I bet you're the kind of person that set their ATM pin as 1234");
								$passquestion = true;
							} elseif ($confirm == "n") {
								$this->out("At least you're that smart. Granted, not smart enough to think critically about using your root account for a database plugin, but smart enough to know to password protect it.");
							} else {
								$this->out("I know that concussion is killing you, but let's try to get through this.");
							}
						} else {
							$confirm = $this->in("So, let's get this straight. Your account, which you use to administer this application which will be the next Grindr, has no password? (y/n)");
							if ($confirm == "y") {
								$this->out("Remind me not to buy into the IPO.");
								$passquestion = true;
							} elseif ($confirm == "n") {
								$this->out("Let's hope not.");
							} else {
								$this->out("Remember to name me in your last will & testament.");
							}
						}
					} else {
						if ($sshuser == "root") {
							$confirm = $this->in("Okay, so you just gave me your root password. But, is it correct? (y/n)");
							if ($confirm == "y") {
								$this->out("Hey, maybe after this, I can get your bank account number?");
								$passquestion = true;
							} elseif ($confirm == "n") {
								$this->out("Well, at least that's progress. But we do need a password to continue.");
							} else {
								$this->out("I get the feeling Mavis Beacon is turning in her grave.");
							}
						} else {
							$confirm = $this->in("Okay, so it's not your root password. :( But, is it correct? (y/n)");
							if ($confirm == "y") {
								$this->out("Awesome. Can you imagine the flak I'm giving people who did give me their root password?");
								$passquestion = true;
							} elseif ($confirm == "n") {
								$this->out("Okay, let's try this again.");
							} else {
								$this->out("It's evident why they didn't trust you with the root password.");
							}
						}
					}
			    }
			    $this->out("Okay, so we'll try to test the connection using these details.");
			    $connection = ssh2_connect($sshhost . ":" . $sshport);
			    if (ssh2_auth_password($connection, $sshuser, $sshpass)) {
				    $this->out("Well, it seemed to work. Hooray.");
				    Configure::write('SSH.Host', $sshhost);
				    Configure::write('SSH.Port', $sshport);
				    Configure::write('SSH.User', $sshuser);
				    Configure::write('SSH.Password', crypt($sshpass, Configure::read('Security.salt')));
				    if ($hostkeyquestion == "y") {
					    Configure::write('SSH.Fingerprint',ssh2_fingerprint($connection, SSH2_FINGERPRINT_MD5));
				    }
				    Configure::write('Errata.SSHConn', 'UserPassCombo');
				    Configure::dump('mongodb', 'default', ['Errata', 'SSH']);
			    } else {
				    $this->out("Nope, as I predicted, it wouldn't work. Tragically, ssh2_auth_password() won't tell me WHY you failed, but you failed.");
				    $this->out("You should take a time out and think about what you did.");
				    $this->out("In fact, I'm not clearing your console so passers by can see just how badly you failed.");
				    $this->abort("Come back when you decide not to waste my time.", 666);
			    }
		    } else {
			    $this->out("Normally, you would define your paths in the config/app.php file. Using this process though, we'll add them to the server's SSH keyring.");
			    $this->out("Right now, this tool only accepts RSA encrypted keys. I'll add the function to work with other keys later.");
			    $pubkeyquestion = false;
			    while (!$pubkeyquestion) {
				    $sshpubkey = $this->in("I'm going to need the path to your public key. Please enter a valid path.");
				    $sshpublickey->open($sshpubkey);
				    if (!$sshpublickey->exists()) {
					    $this->out("What are you talking about? There's no key there!");
				    } elseif ($sshpubkey == "") {
					    $this->out("You mean to tell me you forgot? Seriously, where's the key?");
				    } elseif (!$sshpublickey->readable()) {
					    $this->abort("Um, that file wasn't readable. You might want to check on that. The file permissions were set to " . fileperms($sshpubkey) . " and the file owner is set to ". fileowner($sshpubkey) .".");
				    } else {
					    $this->out("Okay, it seems like the file might work.");
					    $pubkeyquestion = true;
				    }
			    }
			    
			    $privatekeyquestion = false;
			    while (!$privatekeyquestion) {
				    $sshprikey = $this->in("I'm going to need the path to your private key now. Please enter a valid path.");
				    if (!file_exists($sshprikey)) {
					    $this->out("What are you talking about? There's no key there!");
				    } elseif ($sshprikey == "") {
					    $this->out("You mean to tell me you forgot? Seriously, where's the key?");
				    } elseif (!is_readable($sshprikey)) {
					    $this->abort("Um, that file wasn't readable. You might want to check on that. The file permissions were set to " . fileperms($sshprikey) . " and the file owner is set to ". fileowner($sshprikey) .".");
				    } else {
					    $this->out("Okay, it seems like the file might work.");
					    $pubkeyquestion = true;
				    }
			    }
			    $passphrasequestion = false;
			    while (!$passphrasequestion) {
				    $sshpass = $this->in("And now, we need to get the passphrase to decrypt the key.");
				    if ($sshpass == "") {
					    $confirm = $this->in("Seriously? You didn't add a passphrase? (y/n)");
					    if ($confirm == "y") {
						    $this->out("Oi vey.");
						    $passphrasequestion = true;
					    } elseif ($confirm == "n") {
						    $this->out("Okay, good. So, let's try this again.");
					    } else {
						    $this->out("I'm just going to assume you meant no.");
					    }
				    } else {
					    $this->out("Okay. We'll try to see if this works.");
				    }
			    }
			    $keyserve = ssh2_publickey_init($connection);
			    $pubkeytext = $sshpublickey->read();
			    $pubkeytext = chop($pubkeytext,"ssh-rsa\n");
			    $pubkeytext = explode("\n", $pubkeytext);
			    $pubkeytext = $pubkeytext[0];
			    $pubkeytext = base64_decode($pubkeytext);
			    $pubkeyattributes = array(
				    'comments' => 'Placed on the keyring by mongodb-cakephp3 plugin. If the plugin is still installed, use "bin\cake mongo ssh-remove" in terminal to remove the key automatically. See https://github.com/kawaiidesune/mongodb-cakephp3 for details.',
				    'host' => $sshhost,
				    'username' => $sshuser
			    );
			    if (ssh2_publickey_add($keyserve, "ssh-rsa", $pubkeytext, false, $pubkeyattributes)) {
				    $this->out("The public key was added to the SSH2 keyring successfully.");
			    } else {
				    $this->abort("Something went wrong with adding the public key to the SSH2 keyring.");
			    }
			    if (ssh2_auth_agent($connection, $sshuser)) {
				    Configure::write('SSH.Host', $sshhost);
				    Configure::write('SSH.Port', $sshport);
				    Configure::write('SSH.User', $sshuser);
				    if ($hostkeyquestion == "y") {
					    Configure::write('SSH.Fingerprint',ssh2_fingerprint($connection, SSH2_FINGERPRINT_MD5));
				    }
				    Configure::write('Errata.SSHConn', 'PublicPrivateKeyCombo');
				    Configure::dump('mongodb', 'default', ['Errata', 'SSH']);
			    } else {
				    $this->abort("Something went wrong with the connection. Unfortunately, ssh2_auth_agent() won't tell me because it only returns a boolean value...");
			    }
			    $this->out("If you made it this far, congrats. The connection worked. Now, let's configure the rest of the details.")
		    } // This ends the if ($method) statement.
	    } // This ends the if ($ssh) statement. Now, we can get back to configuring the rest of the details concerning MongoDB.
	    $hostquestion = false;
	    while ($hostquestion == false) {
		    $host = $this->in("Okay, so where is your MongoDB installation located (press enter to accept the default, localhost)");
		    if ($host == "") {
			    $this->verbose("Setting the MongoDB host name to localhost.");
		    } elseif (!isValidDomain($host)) {
			    $this->out("Domain name appears to be invalid.");
		    } else {
			    $this->verbose("Setting the MongoDB host name to " . $host);
		    }
	    }
	    
	    $dbquestion = false;
	    while ($dbquestion == false) {
		    $db = $this->in("What is the name of the collection that you intend to use?");
		    if ($db == "") {
			    $this->out("We are going to need a collection name...");
		    } else {
			    $this->verbose("Collection name will be set to " . $db);
			    $dbquestion = true;
		    }
	    }
	    
	    // TODO: Add some code to test the connection and write the variables to config/mongodb.php
    }
    protected function isValidDomain($domain) {
	    return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) //valid chars check
            && preg_match("/^.{1,253}$/", $domain_name) //overall length check
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)   ); //length of each label
    }
    public function getOptionParser() {
	    $parser = parent::getOptionParser();
	    $parser->addSubcommand(
		    'init' => 'A tool to initialize the variables to allow a connection to MongoDB.'
	    );
    }
}
?>