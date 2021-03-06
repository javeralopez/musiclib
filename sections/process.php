<?php
	$allowed_extensions = array("jpeg", "jpg", "png");
	require_once "sections/functions.php";

	# Status variables
	$_SESSION['loggedIn'] = false;
	$_SESSION['signedIn'] = false;
	$_SESSION['commentPosted'] = false;
	$_SESSION['commentUpdated'] = false;
	$_SESSION['commentDeleted'] = false;
	$_SESSION['gradeGiven'] = false;
	$_SESSION['gradeUpdated'] = false;
	$_SESSION['gradeDeleted'] = false;
	$_SESSION['messageSent'] = false;
	$_SESSION['artistAdded'] = false;
	$_SESSION['albumAdded'] = false;

	$_SESSION['error'] = false;

	# Process log in
	if ( isset( $_POST['login'] ) 
	&& empty( $_POST['login'] ) 
	&& !empty( $_POST['username'] ) 
	&& !empty( $_POST['password'] ) ) :
		$user = addslashes( htmlspecialchars( $_POST['username'] ) );
		$passwd = addslashes( htmlspecialchars( $_POST['password'] ) );
		$_SESSION['user'] = new User(User::login( $user, $passwd ));
		$_SESSION['online'] = true;
		$_SESSION['loggedIn'] = true;
	endif;

	# Process log out
	if ( isset( $_POST['logout'] )
	&& empty( $_POST['logout'] ) ) :
		User::logout();
		header("Location: ./");
	endif;

	# Process sign in
	if ( isset( $_POST['signin'] ) 
	&& empty( $_POST['signin'] ) 
	&& !empty( $_POST['username'] ) 
	&& !empty( $_POST['email'] ) 
	&& !empty( $_POST['password'] ) 
	&& !empty( $_POST['password-confirm'] ) 
	&& $_POST['password'] === $_POST['password-confirm'] ) :
		$user = addslashes( htmlspecialchars( $_POST['username'] ) );
		$email = addslashes( htmlspecialchars( $_POST['email'] ) );
		$passwd = addslashes( htmlspecialchars( $_POST['password'] ) );
		User::create( $user, $email, $passwd );
		$_SESSION['user'] = new User( User::login( $user, $passwd ) );
		$_SESSION['online'] = true;
		$_SESSION['signedIn'] = true;
	endif;

	# Process comment
	if ( isset( $_POST['comment'] )
	&& isset( $_POST['song'] )
	&& is_numeric( $_POST['song'] )
	&& isset( $_POST['text'] )
	&& !empty( $_POST['text'] )
	&& preg_match( "/[a-zA-Z0-9]/", trim( $_POST['text'] ) )
	&& isset( $_SESSION['online'] )
	&& $_SESSION['online'] ) :
		$db = $_SESSION['db'];
		$song = new Song( $_POST['song'] );
		$user_id = $_SESSION['user']->getId();
		$text = preg_replace( "/_3/", "&hearts;", htmlspecialchars( trim( preg_replace( "/<3/", "_3", $_POST['text'] ) ) ) );
		$stmt = $song->userHasCommented( $user_id ) 
		? $db->prepare( "update comment set text = :text, date = unix_timestamp() where user = :user and song = :song;" ) 
		: $db->prepare( "insert into comment (user, song, text, date) values (:user, :song, :text, unix_timestamp());" );
		$stmt->execute( array(
			"user" => $user_id,
			"song" => $song->getId(),
			"text" => $text
		) );
		$stmt->closeCursor();
		$_SESSION['commented'] = true;
	endif;

	# Process regular search
	/**
	 * @author Jérôme Boesch
	 * 
	 */
	if ( isset($_GET['q']) && (!empty( $_GET['q'])) ):
		$db = $_SESSION['db'];
		$q = htmlspecialchars( $_GET['q'] );

		$search_songs = array();
		$search_albums = array();
		$search_artists = array();
		$search_users = array();

		# Songs
		$stmt = $db->prepare( "select id from song where title like convert(_utf8 ? using utf8) collate utf8_general_ci order by title;" );
		$stmt->execute( array(
			'%'.$q.'%'
		) );

		while ($song_result = $stmt->fetch(PDO::FETCH_NUM)) {
			$search_songs[] = new Song($song_result[0]);
		}

		$stmt->closeCursor();

		# Albums
		$stmt = $db->prepare( "select id from album where name like convert(_utf8 ? using utf8) collate utf8_general_ci order by name;" );
		$stmt->execute( array(
			'%'.$q.'%'
		) );

		while ($search_albums_result = $stmt->fetch(PDO::FETCH_NUM)) {
			$search_albums[] = new Album($search_albums_result[0]);
		}

		$stmt->closeCursor();


		# Artist
		$stmt = $db->prepare( "select id from artist where name like convert(_utf8 ? using utf8) collate utf8_general_ci order by name;" );
		$stmt->execute( array(
			'%'.$q.'%'
		) );

		while ($search_artists_result = $stmt->fetch(PDO::FETCH_NUM)) {
			$search_artists[] = new Artist($search_artists_result[0]);
		}

		$stmt->closeCursor();


		# Users
		$stmt = $db->prepare( "select id from user where username like convert(_utf8 ? using utf8) collate utf8_general_ci order by username;" );
		$stmt->execute( array(
			'%'.$q.'%'
		) );

		while ($search_users_result = $stmt->fetch(PDO::FETCH_NUM)) {
			$search_users[] = new User($search_users_result[0]);
		}

		$stmt->closeCursor();

if ((count($search_songs)+count($search_albums)+count($search_artists)+count($search_users))===1) {
		if (count($search_songs)==1):
			Page::goSong($search_songs[0]->getId());
		endif;
		if (count($search_artists)==1):
			Page::goArtist($search_artists[0]->getId());
		endif;
		if (count($search_albums)==1):
			Page::goAlbum($search_albums[0]->getId());
		endif;
		if (count($search_users)==1):
			Page::goUser($search_users[0]->getId());
		endif;
		}

	endif;
	
	/**
	 * @author Jérôme Boesch 
	 *
	 */
	if( isset( $_POST['text'] ) && isset( $_POST['reason'] ) && !empty( $_POST['text'] ) 
		&& isset( $_SESSION['online'] ) && $_SESSION['online'] 
		&& is_numeric( $_POST['reason'] ) && Reason::exists( $_POST['reason'] ) ):
		$db = $_SESSION['db'];
	
		$user = $_SESSION['user']->getId();
		$reason = $_POST['reason'];
		$text = $_POST['text'];

		$stmt = $db->prepare( "insert into message (user, text, date, reason) values (:user, :text, unix_timestamp(), :reason);" );
		$stmt->execute( array(
			':user' => $user,
			':text' => $text,
			':reason' => $reason
		) );

		$stmt->closeCursor();
		$_SESSION['messageSent'] = true;
	endif;

	# Process add artist
	/**
	 *
	 * @author Antoine De Gieter
	 *
	 */
	if ( isset( $_POST['add_artist'] )
	&& isset ( $_POST['name'] )
	&& isset ( $_POST['biography'] )
	&& isset( $_SESSION['online'] ) 
	&& $_SESSION['online'] ):
		$picture = htmlspecialchars( $_POST['picture'] );
		$name = utf8_decode( htmlspecialchars( $_POST['name'] ) );
		$biography = utf8_decode( trim( htmlspecialchars( $_POST['biography'] ) ) );
		$picture_name = explode( ".", $_FILES["picture"]["name"] );
		$extension = end( $picture_name );

		if ( is_uploaded_file( $_FILES["picture"]["tmp_name"] ) 
		&& isset( $_FILES["picture"] ) 
		&& $_FILES["picture"]['error'] === 0 
		&& in_array( $extension, $allowed_extensions ) ):
			$picture_name = strtolower( normalize( preg_replace("/[ '\"\/]/", "", $name) ) ).'.'.$extension;
			$tmp_name = $_FILES["picture"]["tmp_name"];

			$path = "./img/artists/".$picture_name;
			move_uploaded_file( $tmp_name, $path );
			$_SESSION['artistAdded'] = true;
			Page::goArtist( Artist::create( $name, $biography, $_SESSION['user']->getId(), $picture_name ) );
		else:
			$_SESSION['error'] = true;
		endif;
	endif;