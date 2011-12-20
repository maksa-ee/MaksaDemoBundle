<?php

/*
 * Copyright (c) 2011 "Cravler", http://github.com/cravler
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:

 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Maksa\DemoBundle\Controller;

use \Symfony\Bundle\FrameworkBundle\Controller\Controller;
use \Symfony\Component\HttpFoundation\Response;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use \Maksa\Bundle\UlinkBundle\Exception\UlinkException;

use \Maksa\DemoBundle\Entity\Order;

/**
 * @author Cravler <http://github.com/cravler>
 */
class DemoController extends Controller
{
    /**
     * @Route("/")
     * @Template()
     */
    public function requestAction()
    {
        /* @var \Maksa\Bundle\UlinkBundle\Service $ulink */
        $ulink = $this->get('maksa_ulink.service');

        $em      = $this->getDoctrine()->getEntityManager();
        $session = $this->getRequest()->getSession();

        $order   = null;
        $orderId = $session->get('maksa_demo_order_id');

        // find order
        if ($orderId) {
            /* @var \Maksa\DemoBundle\Entity\Order $order */
            $order = $em->getRepository('MaksaDemoBundle:Order')->find($orderId);
            if ($order && Order::STATUS_WAIT !== $order->getStatus()) {
                $order = null;
            }
        }

        // create new order
        if (!$order) {
            $order = new Order;
            $em->persist($order);
            $em->flush();
            $session->set('maksa_demo_order_id', $order->getId());
        }

        $items = array(
            array(
                'name'         => '11" MacBook Air',
                'description'  => '1.6GHz dual-core Intel Core i5 processor, 4GB memory, 128GB flash storage1, Intel HD Graphics 3000',
                'oneItemPrice' => '1149',
                'quantity'     => 1,
            ),
            array(
                'name'         => 'iPad 2 with Wi-Fi + 3G',
                'description'  => 'Black cover 64 gb storage',
                'oneItemPrice' => '799',
                'quantity'     => 2,
            )
        );

        $amount = 0;
        foreach($items as $item) {
            $amount += $item['oneItemPrice'] * $item['quantity'];
        }

        $rawData = $ulink->encrypt(
            array(
                'clientTransactionId' => $order->getId(),
                'amount'              => $amount,
                'order'               => $items,
                'goBackUrl'           => $this->generateUrl('maksa_go_back', array('orderId' => $order->getId()), true),
                'responseUrl'         => $this->generateUrl('maksa_response', array(), true) . '?type=plain',
            )
        );

        $request = $this->getRequest();
        $domain  = urlencode($request->query->get('domain', 'maksa.ee'));

        return array(
            'signedRequest' => $rawData,
            'items'         => $items,
            'amount'        => $amount,
            //'link'        => 'https://maksa.ee/pay',
            'link'          => 'https://'.$domain.'/pay/test',
        );
    }

    /**
     * @Route("/goback/{orderId}", name="maksa_go_back")
     * @Template()
     */
    public function goBackAction($orderId)
    {
        // find order
        $em = $this->getDoctrine()->getEntityManager();
        /* @var \Maksa\DemoBundle\Entity\Order $order */
        $order = $em->getRepository('MaksaDemoBundle:Order')->find($orderId);

        if (null === $order) {
            $this->createNotFoundException(sprintf('Order with id "%s" not found.', $orderId));
        }

        return array(
            'status' => (
                Order::STATUS_WAIT === $order->getStatus() ?
                    'wait' : (
                        Order::STATUS_SUCCESS === $order->getStatus() ?
                            'success' : 'failure'
                    )
            ),
            'link'   => $this->generateUrl('maksa_wait', array('orderId' => $order->getId())),
        );
    }

    /**
     * @Route("/wait/{orderId}", name="maksa_wait")
     */
    public function waitAction($orderId)
    {
        $response = array(
            'status' => 'wait',
        );

        // find order
        $em = $this->getDoctrine()->getEntityManager();
        /* @var \Maksa\DemoBundle\Entity\Order $order */
        $order = $em->getRepository('MaksaDemoBundle:Order')->find($orderId);

        if ($order && Order::STATUS_WAIT !== $order->getStatus()) {
            $response['status'] = (Order::STATUS_SUCCESS === $order->getStatus() ? 'success' : 'failure');
        }

        $response = new Response(json_encode($response));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/response", name="maksa_response")
     * @Template()
     */
    public function responseAction()
    {
        $request  = $this->getRequest();
        $rawData  = $request->request->get('signedResponse');
        $response = array('status' => 'NOTOK');

        if ($request->getMethod() == 'POST' && $rawData) {
            /* @var $ulink \Maksa\Bundle\UlinkBundle\Service */
            $ulink = $this->get('maksa_ulink.service');

            try {
                $responseData = $ulink->decrypt($rawData);

                $testPayment = true;
                if (isset($responseData['isTest']) && false === $responseData['isTest']) {
                    // normal payment
                    $testPayment = false;
                }
                $response['isTest'] = $testPayment;

                // find order
                $em = $this->getDoctrine()->getEntityManager();
                /* @var \Maksa\DemoBundle\Entity\Order $order */
                $order = $em->getRepository('MaksaDemoBundle:Order')->find($responseData['clientTransactionId']);

                if (null === $order) {
                    $this->createNotFoundException(sprintf('Order with id "%s" not found.', $responseData['clientTransactionId']));
                }

                // payment success
                if ($responseData['success']) {
                    // do something
                    $order->setStatus(Order::STATUS_SUCCESS);

                    $response['msg'] = 'Payment success.';
                }
                // payment failure
                else {
                    // do something
                    $order->setStatus(Order::STATUS_FAILURE);

                    $response['msg'] = 'Payment failure.';
                }

                $em->persist($order);
                $em->flush();

                $response['status'] = 'OK';

            } catch (UlinkException $e) {
                // error
                $response['msg'] = $e->getMessage();
            }
        }

        $response = new Response(json_encode($response));

        $type = urlencode($request->query->get('type', 'json'));
        if ('plain' == $type) {
            $response->headers->set('Content-Type', 'text/plain');
        }
        else {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}
