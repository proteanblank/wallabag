<?php

namespace Wallabag\ImportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Wallabag\ImportBundle\Form\Type\UploadImportType;

class DeliciousController extends Controller
{
    /**
     * @Route("/delicious", name="import_delicious")
     */
    public function indexAction(Request $request)
    {
        $form = $this->createForm(UploadImportType::class);
        $form->handleRequest($request);

        $delicious = $this->get('wallabag_import.delicious.import');
        $delicious->setUser($this->getUser());

        if ($this->get('craue_config')->get('import_with_rabbitmq')) {
            $delicious->setProducer($this->get('old_sound_rabbit_mq.import_delicious_producer'));
        } elseif ($this->get('craue_config')->get('import_with_redis')) {
            $delicious->setProducer($this->get('wallabag_import.producer.redis.delicious'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            $markAsRead = $form->get('mark_as_read')->getData();
            $name = 'delicious_' . $this->getUser()->getId() . '.json';

            if (null !== $file && \in_array($file->getClientMimeType(), $this->getParameter('wallabag_import.allow_mimetypes'), true) && $file->move($this->getParameter('wallabag_import.resource_dir'), $name)) {
                $res = $delicious
                    ->setFilepath($this->getParameter('wallabag_import.resource_dir') . '/' . $name)
                    ->setMarkAsRead($markAsRead)
                    ->import();

                $message = 'flashes.import.notice.failed';

                if (true === $res) {
                    $summary = $delicious->getSummary();
                    $message = $this->get('translator')->trans('flashes.import.notice.summary', [
                        '%imported%' => $summary['imported'],
                        '%skipped%' => $summary['skipped'],
                    ]);

                    if (0 < $summary['queued']) {
                        $message = $this->get('translator')->trans('flashes.import.notice.summary_with_queue', [
                            '%queued%' => $summary['queued'],
                        ]);
                    }

                    unlink($this->getParameter('wallabag_import.resource_dir') . '/' . $name);
                }

                $this->get('session')->getFlashBag()->add(
                    'notice',
                    $message
                );

                return $this->redirect($this->generateUrl('homepage'));
            }

            $this->get('session')->getFlashBag()->add(
                'notice',
                'flashes.import.notice.failed_on_file'
            );
        }

        return $this->render('WallabagImportBundle:Delicious:index.html.twig', [
            'form' => $form->createView(),
            'import' => $delicious,
        ]);
    }
}
