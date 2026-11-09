<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Supplier;
use App\Form\SupplierType;
use App\Repository\SupplierRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lists, creates, changes and deletes the suppliers.
 */
#[IsGranted('ROLE_MANAGE_SUPPLIERS')]
#[Route('/admin/suppliers')]
final class SupplierController extends AbstractController
{
    #[Route('', name: 'admin_supplier_index', methods: ['GET'])]
    public function index(SupplierRepository $suppliers): Response
    {
        return $this->render('admin/supplier/index.html.twig', [
            'suppliers' => $suppliers->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_supplier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->edit($request, new Supplier(), $entityManager);
    }

    #[Route('/{id}/edit', name: 'admin_supplier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Supplier $supplier, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($supplier);
            $entityManager->flush();
            $this->addFlash('success', 'supplier.saved');

            return $this->redirectToRoute('admin_supplier_index');
        }

        return $this->render('admin/supplier/edit.html.twig', ['form' => $form, 'supplier' => $supplier]);
    }

    /**
     * A supplier with assignments is not deleted, because the old figures refer to it. The foreign
     * key of the assignments blocks the delete. A count before the delete can miss an assignment
     * that another administrator adds at the same time.
     */
    #[Route('/{id}/delete', name: 'admin_supplier_delete', methods: ['POST'])]
    public function delete(Request $request, Supplier $supplier, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-supplier-'.$supplier->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('The CSRF token is not valid.');
        }

        try {
            $entityManager->remove($supplier);
            $entityManager->flush();
            $this->addFlash('success', 'supplier.deleted');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', 'supplier.delete_blocked');
        }

        return $this->redirectToRoute('admin_supplier_index');
    }
}
