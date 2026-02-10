import React from 'react';
import Tab from "./Tab";
import {UiLabelContext} from "../../Context";

const Navbar = () => {
    const labels = React.useContext(UiLabelContext)
    const tabs = [
        {
            label: "Settings",
            path: "/settings",
            check: ["settings"],
        },
        {
            label: "License",
            path: "/license",
            check: ["license"],
        },
    ]

    return <div className={`flex h-13 w-24 gap-1`}>
        {
            tabs.map((tab) => {
                return <Tab tab={tab} key={tab.label}/>
            })
        }
    </div>
};

export default Navbar;