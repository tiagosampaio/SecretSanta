<?php

namespace Intracto\SecretSantaBundle\Controller;

use Intracto\SecretSantaBundle\Form\WishlistType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Intracto\SecretSantaBundle\Entity\Entry;
use Intracto\SecretSantaBundle\Entity\EmailAddress;

class EntryController extends Controller
{
    /**
     * @DI\Inject("entry_repository")
     * @var \Doctrine\ORM\EntityRepository
     */
    public $entryRepository;

    /**
     * @Route("/entry/{url}", name="entry_view")
     * @Template()
     */
    public function indexAction(Request $request, $url)
    {
        $em = $this->getDoctrine()->getManager();
        $this->getEntry($url);

        $form = $this->createForm(new WishlistType(), $this->entry);

        // Log visit on first access
        if ($this->entry->getViewdate() === null) {
            $this->entry->setViewdate(new \DateTime());
            $em->flush($this->entry);
        }

        if ('POST' === $request->getMethod()) {
            $form->submit($request);
            if ($form->isValid()) {
                $em->flush($this->entry);
                $this->get('session')->getFlashBag()->add(
                    'success',
                    '<h4>Wishlist updated</h4>We\'ve sent out our gnomes to notify your Secret Santa about your wishes!'
                );
            }
        }

        $secret_santa = $this->entry->getEntry();

        return array(
            'entry' => $this->entry,
            'form' => $form->createView(),
            'secret_santa' => $secret_santa,
        );
    }

    /**
     * @Route("/entry/edit-email/{listUrl}/{entryId}", name="entry_email_edit")
     * @Template()
     */
    public function editEmailAction($listUrl, $entryId)
    {
        /** @var Entry $entry */
        $entry = $this->entryRepository->find($entryId);

        if ($entry->getPool()->getListurl() === $listUrl) {
            /** @var \Symfony\Component\Validator\Validator $validatorService */
            $validatorService = $this->get('validator');

            $emailAddress = new EmailAddress($this->getRequest()->request->get('email'));
            $emailAddressErrors = $validatorService->validate($emailAddress);

            if (count($emailAddressErrors) > 0) {
                $this->get('session')->getFlashBag()->add(
                    'error',
                    '<h4>Not saved</h4> There is an error in the email address.'
                );
            } else {
                $em = $this->getDoctrine()->getManager();
                $entry->setEmail((string) $emailAddress);
                $em->flush($entry);

                $entryService = $this->get('intracto_secret_santa.entry_service');
                $entryService->sendSecretSantaMailForEntry($entry);

                $this->get('session')->getFlashBag()->add(
                    'success',
                    '<h4>Saved</h4> The new email address was saved. We also resent the e-mail.'
                );
            }
        }

        return $this->redirect($this->generateUrl('pool_manage', array('listUrl' => $listUrl)));
    }

    /**
     * Retrieve entry by url
     *
     * @param string $url
     *
     * @throws NotFoundHttpException
     * @return boolean
     */
    protected function getEntry($url)
    {
        $this->entry = $this->entryRepository->findOneByUrl($url);

        if (!is_object($this->entry)) {
            throw new NotFoundHttpException();
        }

        return true;
    }
}