package main

import (
	"path/filepath"
	"testing"
)

func TestInvitationRolesAndRevocation(t *testing.T) {
	store, err := OpenStore(filepath.Join(t.TempDir(), "classroom.db"))
	if err != nil {
		t.Fatal(err)
	}
	defer store.Close()

	teacher1, err := store.CreateInvitation("teacher", "101")
	if err != nil {
		t.Fatal(err)
	}
	student, err := store.CreateInvitation("student", "101")
	if err != nil {
		t.Fatal(err)
	}

	if !store.ValidateInvitation(teacher1.Code, "teacher", "101") {
		t.Fatal("teacher invite must be valid")
	}
	if store.ValidateInvitation(teacher1.Code, "student", "101") {
		t.Fatal("teacher invite must not authorize student")
	}
	if store.ValidateInvitation(teacher1.Code, "teacher", "102") {
		t.Fatal("teacher invite must not authorize another lesson")
	}
	if !store.ValidateInvitation(student.Code, "student", "101") {
		t.Fatal("student invite must be valid")
	}

	teacher2, err := store.CreateInvitation("teacher", "101")
	if err != nil {
		t.Fatal(err)
	}
	if store.ValidateInvitation(teacher1.Code, "teacher", "101") {
		t.Fatal("old teacher invite must be revoked")
	}
	if !store.ValidateInvitation(teacher2.Code, "teacher", "101") {
		t.Fatal("new teacher invite must be valid")
	}
	if !store.ValidateInvitation(student.Code, "student", "101") {
		t.Fatal("regenerating teacher invite must not revoke student invite")
	}
}
