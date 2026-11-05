<?php

namespace App\Controller\Admin;

use App\Entity\SubAgreement;
use App\Entity\SubAgreementSupplierAssignment;
use App\Form\AssignmentType;
use App\Supplier\AssignmentDto;
use App\Supplier\AssignmentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Adds, changes and deletes the supplier assignments of a sub-agreement.
 */
#[IsGranted('ROLE_MANAGE_SUPPLIERS')]
final class AssignmentController extends AbstractController
{
    public function __construct(
        private readonly AssignmentManager $manager,
    ) {
    }

    #[Route('/admin/sub-agreements/{id}/assignments/new', name: 'admin_assignment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SubAgreement $subAgreement): Response
    {
        return $this->form($request, AssignmentDto::forNew($subAgreement));
    }

    #[Route('/admin/assignments/{id}/edit', name: 'admin_assignment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SubAgreementSupplierAssignment $assignment): Response
    {
        return $this->form($request, AssignmentDto::forChange($assignment));
    }

    #[Route('/admin/assignments/{id}/delete', name: 'admin_assignment_delete', methods: ['POST'])]
    public function delete(Request $request, SubAgreementSupplierAssignment $assignment): Response
    {
        if (!$this->isCsrfTokenValid('delete-assignment-'.$assignment->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('The CSRF token is not valid.');
        }

        $subAgreement = $assignment->getSubAgreement();
        $this->manager->remove($assignment);
        $this->addFlash('success', 'assignment.deleted');

        return $this->redirectToRoute('admin_sub_agreement_show', ['id' => $subAgreement->getId()]);
    }

    private function form(Request $request, AssignmentDto $data): Response
    {
        $form = $this->createForm(AssignmentType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->manager->save($data);
            $this->addFlash('success', 'assignment.saved');

            return $this->redirectToRoute('admin_sub_agreement_show', ['id' => $data->subAgreement->getId()]);
        }

        return $this->render('admin/assignment/edit.html.twig', ['form' => $form, 'data' => $data]);
    }
}
