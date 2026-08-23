# Application modules

V1 bounded contexts live below this directory and follow the approved
`Domain / Application / Infrastructure / Contracts` structure. This foundation
does not create domain classes early. Delivery code calls Application actions;
modules communicate through public contracts/events and never import another
module's Eloquent model.
