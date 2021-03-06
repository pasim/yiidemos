<?php
require_once(dirname(__FILE__).'/../extensions/runactions/components/ERunActions.php');
/* SendGiftController is used to send gifts*/

class SendGiftController extends AuthController
{
  
  public function filters()
    {
        return array(
            'SendGift + SendGift',
            'SessionLose'
        );
    }
        public function filterSessionLose($filter)
	{
	   $session=new CHttpSession;
           $session->open();
           
          if(!isset($session['me']))
	    { 	
	        $this->redirect(array("auth/logout","token"=>Yii::app()->getRequest()->getCsrfToken()));
	    }
           
	  $filter->run();
	}

 
    public function filterSendGift($filterChain)
    { 
       $session=new CHttpSession;
       $session->open();
       
       if(  !isset($session['id_userorder'])&&
            !isset($session['id_product'])&&
            !isset($session['userid_birthday']))
        {    
    
          $this->redirect(array('auth/index'));  
        }     
     
       if(!isset($session['id_userorder']))
        {  
           if(isset($session['id_product']))
             $this->redirect(array("product/CustomizeGift",'pid'=>$session['id_product']));

           if(isset($session['userid_birthday']))
             $this->redirect(array("product/index",'id'=>$session['userid_birthday']));  
             
        }
          
       $order=UserOrder::model()->findbyPk($session['id_userorder']);  
       if($order==null)
        {
         unset($session['id_userorder']);
         
           if(isset($session['id_product']))
              $this->redirect(array("product/CustomizeGift",'pid'=>$session['id_product']));

           if(isset($session['userid_birthday']))
              $this->redirect(array("product/index",'id'=>$session['userid_birthday'])); 
              
             $this->redirect(array('auth/index'));    
        
        }
       
          
      $filterChain->run();
    }

 public function actionSendGift()
 {
   $session=new CHttpSession;
   $session->open();
   
   $order=UserOrder::model()->with('product')->findbyPk($session['id_userorder']);
    
    
   
   $product=$order->product;

  $model=new SendGiftForm;
  $model->pid=$order->id_product;
   
  $model->wall_post_message='Hey '.$session['selected-username'].', Surprise! I\'ve left a gift for you.  I picked it out myself.  Hope you can use it.';
  
  
  // Yii::app()->session['selected-userid'];
 
   $criteria=new CDbCriteria;
   $criteria->condition='facebook_userid=:facebook_userid';
   $criteria->params=array(':facebook_userid'=>$session['selected-userid']);
   $receiver=User::model()->find($criteria);
  
   if($receiver!=null)
    {
     $model->friends_email=$receiver->facebook_email;
    }   
   $model->notification_email=$session['me']['email'];
 
     
   
   
      if(strtotime(date("Y-m-d"))>strtotime($session['selected-userbirthday'].date('-Y',time())))
       {
         //echo "wrong".$session['selected-userbirthday'];
         //exit;
        $model->delivery=date('M/d/Y',time());
        $date_message="today";
        
          if(date('d',time())==13)
           {
            $date_message="diwali";
           }
       }
      else
       {  
        //echo "ok";
        //exit;
        $year=date('-Y',time());           
        $model->delivery=date('M/d/Y',strtotime($session['selected-userbirthday'].$year));
        $date_message="birthday";
       }

  
    // uncomment the following code to enable ajax-based validation
    /*
    if(isset($_POST['ajax']) && $_POST['ajax']==='send-gift-form-_form-form')
    {
        echo CActiveForm::validate($model);
        Yii::app()->end();
    }
    */

    if(isset($_POST['SendGiftForm']))
    {
        $model->attributes=$_POST['SendGiftForm'];
        
        /* if(!$model->validate())
         {
           $e=$model->getErrors();
           print_r($e);
           exit;
         }  
         */
 
        if($model->validate())
        {
           /*echo "<pre>";
           print_r($model->attributes);
           echo "</pre>"; 
            exit;
             */                   
            // form inputs are valid  - complete the gift details,update fields
            
	
			$order->message_post = $model->wall_post_message;
			$order->receiver_fbemail = $model->friends_email;
			$order->notify_email = $model->notification_email;
			 
                        $delivery_date=substr($model->delivery,7,4).'-'.substr($model->delivery,0,3)."-".substr($model->delivery,4,2);
                        //echo $delivery_date; 
			$model->delivery=date('Y-m-d',strtotime($delivery_date));
                       
                           				
			$order->delivery_date=$model->delivery;


                     /*  echo "deli:".strtotime($modelUserOrder->delivery_date).$modelUserOrder->delivery_date;
                       echo "<br/>vali:".strtotime(date('Y-m-d',time())." +".$product->user_validity." days");
                       echo "<br/>".date('d-m-Y',strtotime(date('Y-m-d',time())." +".$product->user_validity." days"));

                       exit;*/

                        if($order->product->isDateExceeded($model->delivery))
                         {
                           //echo "ok";
                            //exit;

                          $model->addError('delivery','Sorry.This gift is no longer available  after '.$order->product->getProductValidity());
 
                          $model->delivery=date('M/d/Y',strtotime($model->delivery));
                         
                          
                          $this->render("sendgift",array('model'=>$model,'order'=>$order));
                          
                           echo '
                          <script>
                          $(document).ready(function(){
                           centerPopup();
                           loadPopup("Sorry.This gift is no longer available  after '.$order->product->getProductValidity().'");
                          
                           });
                         
                           </script>
                           ';
                          return;

                         }
                         if(strtotime($model->delivery)>strtotime(" +90 days",time()))
                         {
                            // echo "ok";
                            //exit;

                          $model->addError('delivery','Sorry.This gift is no longer available  after '.date('M-d-Y',strtotime(" +90 days",time())));
 
                          $model->delivery=date('M/d/Y',strtotime($model->delivery));
                          $this->render("sendgift",array('model'=>$model,'order'=>$order));
                          
                          echo '
                          <script>
                          $(document).ready(function(){
                           centerPopup();
                           loadPopup("Sorry.This gift is no longer available  after '.date('M-d-Y',strtotime(" +90 days",time())).'");
                          
                           });
                         
                           </script>
                           ';
                          return;

                         }
 

                        $order->save(false);
            
	
		$value=$order->product->price;
		$productdetail = Product::model()->findbyPk($order->id_product);
		$quantity=$productdetail->quantity;
		
		

		if($value>0)
			{
			
					$this->redirect(array('paygift/pay'));
			}

		else
			{
			  
			  
						if(date('d',strtotime($model->delivery)) ==date('d',time()))
						{
						  //echo "ok";
						 // exit;
							
							$this->postToFacebook($session['id_userorder']);
							$fbpoststatus = UserOrder::model()->findbyPk($session['id_userorder']);
							if(($fbpoststatus->facebook_postid == null) and ($fbpoststatus->receiver_fbemail== null))
							{
							    $this->redirect(array('sendGift/error'));
							}
							
							$this->sendMail($session['id_userorder']);
							
						}
				
			$command_order = Yii::app()->db->createCommand();
			$command_order->update('lag_user_order', array(
							'order_status'=>'SUCCESS'
							),
					'id_user_gift=:id_user_gift',array(
									':id_user_gift'=>$session['id_userorder']
									)
				);
			
				if($quantity>0)
					  {
						  
					  $quantity=$quantity-1;
						  
					  $command_product = Yii::app()->db->createCommand();
						  
						  
					  $command_product->update('ps_product', array(
										  'quantity'=>$quantity
										  ),
										  'id_product=:id_product',array(
												  ':id_product'=>$order->id_product,
												  )
							  );
					  }
					  else
					  {
					      $this->redirect(array('auth/index'));
					  }
		     
				$this->redirect(array('sendGift/confirmation'));
				return;
			}

        } //end validity 
    }

    $this->render("sendgift",array('model'=>$model,'order'=>$order,'date_message'=>$date_message));
 }
 
 public function actionError()
 {
    $this->layout="receiver";
    $session=new CHttpSession;
   $session->open();
   $userorder = UserOrder::model()->findbyPk($session['id_userorder']);
   $this->render("error",array('userorder'=>$userorder));
 }
 public function actionConfirmation()
 {
   $session=new CHttpSession;
   $session->open();
   $userorder = UserOrder::model()->findbyPk($session['id_userorder']);
   $this->render("confirmation",array('userorder'=>$userorder));
 }
 private function sendMail($id_user_gift) 
 {
    $session=new CHttpSession;
    $session->open();
   
    $commandPath = Yii::app()->getBasePath() . DIRECTORY_SEPARATOR . 'commands';
    $runner = new CConsoleCommandRunner();
    $runner->addCommands($commandPath);
    $commandPath = Yii::getFrameworkPath() . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'commands';
    $runner->addCommands($commandPath);
    $args = array('yiic', 'sendmail', '--type=instant', '--id='.$id_user_gift);
	ob_start();
    $runner->run($args);
    echo htmlentities(ob_get_clean(), null, Yii::app()->charset);
}

private function postToFacebook($id_user_gift) 
 { 
    $session=new CHttpSession;
    $session->open();
   
    $commandPath = Yii::app()->getBasePath() . DIRECTORY_SEPARATOR . 'commands';
    $runner = new CConsoleCommandRunner();
    $runner->addCommands($commandPath);
    $commandPath = Yii::getFrameworkPath() . DIRECTORY_SEPARATOR . 'cli' . DIRECTORY_SEPARATOR . 'commands';
    $runner->addCommands($commandPath);
    $args = array('yiic', 'postfacebook', '--id='.$id_user_gift);
	ob_start();
    $runner->run($args);
    echo htmlentities(ob_get_clean(), null, Yii::app()->charset);
}
}

?>
