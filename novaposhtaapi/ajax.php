<?
include_once('../../config/config.inc.php');
include_once('../../init.php');
include(dirname(__FILE__).'/novaposhtaapi.php');

$NP = new NovaPoshtaApi();
$sender_city = $NP->NP->getCity($NP->sender_city);
print_r($_POST['cityRecipient']);
$NP->NP->getDocumentPrice($sender_city['data'][0]['Ref'], );
// public function to_check_phone_number_here(){
// 	// code to check phone
// 	if(phone){
// 		return 1;
// 	}else{
// 		return 0;
// 	}
// }
if(isset($_POST['ajax'])){
	// echo function to_check_phone_number_here();
}
?>